<?php

namespace App\Console\Commands;

use App\Models\DatabaseBackup;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;

/**
 * Backup database otomatis (dijadwalkan tiap malam di bootstrap/app.php),
 * lalu hapus backup yang lebih tua dari config('backup.keep_days').
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'Buat backup database (.sql.gz) dan hapus backup lama';

    public function handle(DatabaseBackupService $service): int
    {
        try {
            $backup = $service->run(DatabaseBackup::TRIGGER_SCHEDULED);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $pruned = $service->prune();

        if ($backup->status !== DatabaseBackup::STATUS_SUCCESS) {
            $this->error('Backup gagal: '.$backup->error);

            return self::FAILURE;
        }

        $this->info("Backup selesai: {$backup->filename} ({$backup->humanSize()}). Backup lama dihapus: {$pruned}.");

        return self::SUCCESS;
    }
}
