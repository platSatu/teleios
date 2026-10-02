<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Concerns\VerifiesTransactionPin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Superadmin > Backup Database (3 Oktober 2026): daftar backup, buat
 * manual, download, hapus. Route-nya di grup middleware `superadmin`;
 * buat/download/hapus wajib PIN transaksi dan tercatat di Audit Log,
 * karena file ini berisi SELURUH data aplikasi.
 */
class DatabaseBackupController extends Controller
{
    use VerifiesTransactionPin;

    public function __construct(private readonly DatabaseBackupService $service)
    {
    }

    public function index(): View
    {
        $running = $this->service->isRunning();
        $backups = DatabaseBackup::with('creator')->latest()->paginate(20);
        $diskFree = @disk_free_space(Storage::disk('local')->path(''));

        return view('superadmin.database-backup.index', [
            'backups' => $backups,
            'running' => $running,
            'diskFree' => $diskFree === false ? null : (int) $diskFree,
            'keepDays' => (int) config('backup.keep_days', 14),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($failed = $this->failedTransactionPin($request)) {
            return $failed;
        }

        if ($this->service->isRunning()) {
            return back()->with('error', 'Backup lain sedang berjalan. Tunggu sampai selesai.');
        }

        $user = $request->user();
        AuditLog::record('database_backup.create', DatabaseBackup::class, 'manual', null, null, $user);

        // Dijalankan setelah halaman terkirim supaya browser tidak menunggu lama.
        dispatch(function () use ($user) {
            @set_time_limit(0);

            try {
                app(DatabaseBackupService::class)->run(DatabaseBackup::TRIGGER_MANUAL, $user);
            } catch (RuntimeException) {
                // Backup lain sudah berjalan -- tidak perlu dobel.
            }
        })->afterResponse();

        return back()->with('success', 'Backup sedang dibuat. Muat ulang halaman ini beberapa saat lagi untuk melihat hasilnya.');
    }

    public function download(Request $request, string $id): BinaryFileResponse|RedirectResponse
    {
        if ($failed = $this->failedTransactionPin($request)) {
            return $failed;
        }

        $backup = DatabaseBackup::findOrFail($id);
        $path = $this->service->pathOf($backup);

        if (! $path) {
            return back()->with('error', 'File backup ini tidak tersedia (gagal dibuat atau sudah terhapus).');
        }

        AuditLog::record('database_backup.download', DatabaseBackup::class, $backup->id, null, ['filename' => $backup->filename], $request->user());

        return response()->download($path, $backup->filename, ['Content-Type' => 'application/gzip']);
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        if ($failed = $this->failedTransactionPin($request)) {
            return $failed;
        }

        $backup = DatabaseBackup::findOrFail($id);

        if ($backup->status === DatabaseBackup::STATUS_RUNNING) {
            return back()->with('error', 'Backup yang sedang berjalan tidak bisa dihapus.');
        }

        AuditLog::record('database_backup.delete', DatabaseBackup::class, $backup->id, ['filename' => $backup->filename], null, $request->user());
        $this->service->delete($backup);

        return back()->with('success', 'Backup dihapus.');
    }
}
