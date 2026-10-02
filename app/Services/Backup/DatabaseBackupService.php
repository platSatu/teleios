<?php

namespace App\Services\Backup;

use App\Models\DatabaseBackup;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Backup database MySQL/MariaDB ke file .sql.gz (3 Oktober 2026).
 *
 * - Dijalankan otomatis tiap malam (command backup:database, bootstrap/app.php)
 *   atau manual dari Superadmin > Backup Database.
 * - Password database TIDAK pernah masuk argumen command (bisa terlihat di
 *   daftar proses) -- ditulis ke file opsi sementara (chmod 600) lalu dihapus.
 * - Output mysqldump langsung dikompres ke file .partial, baru di-rename
 *   setelah mysqldump selesai & menulis penanda "Dump completed", jadi tidak
 *   ada file setengah jadi yang terlihat sebagai backup sukses.
 * - Hanya satu backup boleh berjalan sekaligus (cache lock).
 */
class DatabaseBackupService
{
    /** Backup yang "running" lebih lama dari ini dianggap terhenti (proses mati). */
    private const STALE_MINUTES = 120;

    private const LOCK_KEY = 'database-backup';

    public function isRunning(): bool
    {
        $this->markStale();

        return DatabaseBackup::where('status', DatabaseBackup::STATUS_RUNNING)->exists();
    }

    public function run(string $trigger, ?User $user = null): DatabaseBackup
    {
        $lock = Cache::lock(self::LOCK_KEY, self::STALE_MINUTES * 60);

        if (! $lock->get()) {
            throw new RuntimeException('Backup lain sedang berjalan. Tunggu sampai selesai.');
        }

        $this->markStale();

        $filename = 'backup-'.now()->format('Y-m-d-His').'.sql.gz';
        $backup = DatabaseBackup::create([
            'status' => DatabaseBackup::STATUS_RUNNING,
            'trigger' => $trigger,
            'created_by' => $user?->id,
            'started_at' => now(),
        ]);

        $disk = Storage::disk('local');
        $relative = trim(config('backup.directory', 'backups'), '/').'/'.$filename;
        $final = $disk->path($relative);
        $partial = $final.'.partial';

        try {
            if (! is_dir(dirname($final))) {
                mkdir(dirname($final), 0750, true);
            }

            $this->dumpTo($partial);

            if (! rename($partial, $final)) {
                throw new RuntimeException('File backup tidak bisa disimpan.');
            }

            $backup->update([
                'status' => DatabaseBackup::STATUS_SUCCESS,
                'filename' => $filename,
                'size_bytes' => filesize($final) ?: 0,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            @unlink($partial);
            Log::error('database-backup: gagal', ['backup_id' => $backup->id, 'error' => $e->getMessage()]);

            $backup->update([
                'status' => DatabaseBackup::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);
        } finally {
            $lock->release();
        }

        return $backup->fresh();
    }

    /** Path lengkap file backup sukses; null kalau filenya sudah tidak ada. */
    public function pathOf(DatabaseBackup $backup): ?string
    {
        if (! $backup->isDownloadable() || ! preg_match('/^backup-[0-9-]+\.sql\.gz$/', $backup->filename)) {
            return null;
        }

        $relative = trim(config('backup.directory', 'backups'), '/').'/'.$backup->filename;

        return Storage::disk('local')->exists($relative) ? Storage::disk('local')->path($relative) : null;
    }

    public function delete(DatabaseBackup $backup): void
    {
        if ($path = $this->pathOf($backup)) {
            @unlink($path);
        }

        $backup->delete();
    }

    /**
     * Hapus backup lebih tua dari keep_days. Backup sukses TERAKHIR selalu
     * disimpan, walaupun sudah tua (supaya tidak pernah tersisa nol backup).
     */
    public function prune(): int
    {
        $keepDays = max(1, (int) config('backup.keep_days', 14));
        $latestSuccessId = DatabaseBackup::where('status', DatabaseBackup::STATUS_SUCCESS)->latest()->value('id');

        $old = DatabaseBackup::where('created_at', '<', now()->subDays($keepDays))
            ->where('status', '!=', DatabaseBackup::STATUS_RUNNING)
            ->when($latestSuccessId, fn ($q) => $q->where('id', '!=', $latestSuccessId))
            ->get();

        $old->each(fn (DatabaseBackup $backup) => $this->delete($backup));

        return $old->count();
    }

    /** Proses yang mati di tengah jalan (server restart dsb.) ditandai gagal. */
    private function markStale(): void
    {
        DatabaseBackup::where('status', DatabaseBackup::STATUS_RUNNING)
            ->where('started_at', '<', now()->subMinutes(self::STALE_MINUTES))
            ->update([
                'status' => DatabaseBackup::STATUS_FAILED,
                'error' => 'Proses backup terhenti sebelum selesai.',
                'finished_at' => now(),
            ]);
    }

    private function dumpTo(string $target): void
    {
        $connection = config('database.connections.'.config('database.default'));

        if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Backup hanya mendukung database MySQL/MariaDB.');
        }

        $optionsFile = $this->writeOptionsFile($connection);

        try {
            $process = new Process([
                config('backup.mysqldump', 'mysqldump'),
                '--defaults-extra-file='.$optionsFile, // wajib argumen pertama
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--hex-blob',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                $connection['database'],
            ]);
            $process->setTimeout(3600);

            $gz = gzopen($target, 'wb6');

            if ($gz === false) {
                throw new RuntimeException('File backup tidak bisa dibuat (cek izin folder storage).');
            }

            $stderr = '';
            $tail = '';

            $process->run(function (string $type, string $buffer) use ($gz, &$stderr, &$tail) {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                    $tail = substr($tail.$buffer, -256);
                } else {
                    $stderr .= $buffer;
                }
            });

            gzclose($gz);

            if (! $process->isSuccessful()) {
                throw new RuntimeException('mysqldump gagal: '.(trim($stderr) ?: 'kode '.$process->getExitCode()));
            }

            // mysqldump selalu menutup dump yang utuh dengan baris ini.
            if (! str_contains($tail, 'Dump completed')) {
                throw new RuntimeException('Hasil mysqldump tidak lengkap.');
            }
        } finally {
            @unlink($optionsFile);
        }
    }

    /** File opsi [client] sementara berisi kredensial (chmod 600), dihapus setelah dump. */
    private function writeOptionsFile(array $connection): string
    {
        $quote = fn ($value) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $value).'"';

        $lines = ['[client]', 'user='.$quote($connection['username']), 'password='.$quote($connection['password'] ?? '')];

        if (! empty($connection['unix_socket'])) {
            $lines[] = 'socket='.$quote($connection['unix_socket']);
        } else {
            $lines[] = 'host='.$quote($connection['host']);
            $lines[] = 'port='.(int) ($connection['port'] ?? 3306);
        }

        $path = tempnam(sys_get_temp_dir(), 'dbbackup');
        chmod($path, 0600);
        file_put_contents($path, implode("\n", $lines)."\n");

        return $path;
    }
}
