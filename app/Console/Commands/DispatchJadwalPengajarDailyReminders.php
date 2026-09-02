<?php

namespace App\Console\Commands;

use App\Jobs\SendJadwalPengajarReminder;
use App\Models\JadwalKelas;
use App\Models\JadwalPengajarReminderLog;
use App\Models\JadwalReminderSetting;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * H-1 OTOMATIS: rekap jadwal BESOK ke tiap pengajar yang punya sesi
 * aktif besok, satu pesan per pengajar (Jadwal v2, CLAUDE.md item #15
 * spec poin 9). Dijadwalkan sore hari (lihat bootstrap/app.php) --
 * "besok" dihitung dari tanggal command ini jalan. Idempotent lewat
 * App\Models\JadwalPengajarReminderLog (unique pengajar_id+reminder_date)
 * dengan pola klaim race-safe yang sama seperti App\Console\Commands\
 * DispatchDueJadwalReminders::claimAndDispatch() -- aman kalau
 * dijalankan berulang untuk tanggal yang sama.
 */
class DispatchJadwalPengajarDailyReminders extends Command
{
    protected $signature = 'jadwal:dispatch-pengajar-reminders';

    protected $description = 'Enqueue rekap WA H-1 ke tiap pengajar yang punya sesi aktif besok';

    public function handle(): int
    {
        $tomorrow = now()->addDay()->toDateString();
        $count = 0;

        JadwalReminderSetting::query()
            ->where('enabled', true)
            ->where('remind_notify_pengajar', true)
            ->whereNotNull('device_id')
            ->chunkById(50, function ($settings) use ($tomorrow, &$count) {
                foreach ($settings as $setting) {
                    $pengajarIds = JadwalKelas::where('company_id', $setting->company_id)
                        ->where('status', JadwalKelas::STATUS_ACTIVE)
                        ->whereDate('start_time', $tomorrow)
                        ->distinct()
                        ->pluck('pengajar_id');

                    foreach ($pengajarIds as $pengajarId) {
                        $count += $this->claimAndDispatch($setting->company_id, $pengajarId, $tomorrow);
                    }
                }
            });

        $this->info("Dispatched {$count} rekap pengajar untuk tanggal {$tomorrow}.");

        return self::SUCCESS;
    }

    private function claimAndDispatch(string $companyId, string $pengajarId, string $date): int
    {
        $claimed = DB::transaction(function () use ($companyId, $pengajarId, $date) {
            $find = fn () => JadwalPengajarReminderLog::where('pengajar_id', $pengajarId)
                ->where('reminder_date', $date)
                ->lockForUpdate()
                ->first();

            if ($find()) {
                return false;
            }

            try {
                JadwalPengajarReminderLog::create([
                    'company_id' => $companyId,
                    'pengajar_id' => $pengajarId,
                    'reminder_date' => $date,
                    'status' => JadwalPengajarReminderLog::STATUS_PENDING,
                ]);

                return true;
            } catch (QueryException $e) {
                if ($find()) {
                    return false;
                }

                throw $e;
            }
        });

        if (! $claimed) {
            return 0;
        }

        SendJadwalPengajarReminder::dispatch($companyId, $pengajarId, $date);

        return 1;
    }
}
