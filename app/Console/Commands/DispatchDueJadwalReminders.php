<?php

namespace App\Console\Commands;

use App\Jobs\SendJadwalReminder;
use App\Models\JadwalKelas;
use App\Models\JadwalKelasReminderLog;
use App\Models\JadwalReminderSetting;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Jadwal-only equivalent of App\Console\Commands\
 * DispatchDueWaMessageSchedules -- berjalan terpisah total (lihat
 * bootstrap/app.php's ->withSchedule()), tidak berbagi kelas/tabel
 * dengan command Chat itu. "Due" di sini artinya start_time sebuah
 * App\Models\JadwalKelas sudah masuk jendela remind_value/remind_unit
 * MILIK COMPANY-nya sendiri (lihat App\Models\JadwalReminderSetting::
 * remindMinutesBefore() -- tiap company bisa beda, sesuai diskusi:
 * tempat kursus berbeda punya kebutuhan berbeda) tapi belum lewat
 * (start_time masih di masa depan -- kelas yang sudah lewat tidak
 * perlu diingatkan lagi).
 *
 * Sama seperti DispatchDueWaMessageSchedules, command ini cuma
 * MENGKLAIM pekerjaan (lewat App\Models\JadwalKelasReminderLog, race-
 * safe dengan pola locked-lookup + catch QueryException yang sama
 * persis) -- App\Jobs\SendJadwalReminder yang benar-benar mengirim,
 * termasuk guard kategori package Chat/WhatsApp-nya sendiri.
 */
class DispatchDueJadwalReminders extends Command
{
    protected $signature = 'jadwal:dispatch-due-reminders';

    protected $description = 'Enqueue due Jadwal Kelas reminders (WA) berdasarkan pengaturan pengingat tiap company';

    public function handle(): int
    {
        $now = now();
        $count = 0;

        JadwalReminderSetting::query()
            ->where('enabled', true)
            ->whereNotNull('device_id')
            ->chunkById(50, function ($settings) use ($now, &$count) {
                foreach ($settings as $setting) {
                    $dueBefore = $now->copy()->addMinutes($setting->remindMinutesBefore());

                    $due = JadwalKelas::query()
                        ->where('company_id', $setting->company_id)
                        ->where('status', JadwalKelas::STATUS_ACTIVE)
                        ->whereNotNull('start_time')
                        ->where('start_time', '>', $now)
                        ->where('start_time', '<=', $dueBefore)
                        ->whereDoesntHave('reminderLog')
                        ->get(['id', 'company_id']);

                    foreach ($due as $kelas) {
                        $count += $this->claimAndDispatch($kelas->id, $kelas->company_id);
                    }
                }
            });

        $this->info("Dispatched {$count} due Jadwal reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Race-safe claim -- pola identik App\Console\Commands\
     * DispatchDueWaMessageSchedules::claimAndDispatch() (lihat
     * docblock-nya), disederhanakan karena kunci uniknya cuma
     * jadwal_kelas_id (bukan komposit schedule+recipient+day+step).
     */
    private function claimAndDispatch(string $jadwalKelasId, string $companyId): int
    {
        $claimed = DB::transaction(function () use ($jadwalKelasId, $companyId) {
            $find = fn () => JadwalKelasReminderLog::where('jadwal_kelas_id', $jadwalKelasId)
                ->lockForUpdate()
                ->first();

            if ($find()) {
                return false;
            }

            try {
                JadwalKelasReminderLog::create([
                    'jadwal_kelas_id' => $jadwalKelasId,
                    'company_id' => $companyId,
                    'status' => JadwalKelasReminderLog::STATUS_PENDING,
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

        SendJadwalReminder::dispatch($jadwalKelasId);

        return 1;
    }
}
