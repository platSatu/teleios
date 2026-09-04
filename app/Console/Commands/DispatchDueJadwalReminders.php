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
 * App\Models\JadwalKelas sudah masuk jendela sebuah App\Models\
 * JadwalReminderRule MILIK COMPANY-nya sendiri tapi belum lewat
 * (start_time masih di masa depan -- kelas yang sudah lewat tidak
 * perlu diingatkan lagi).
 *
 * Sama seperti DispatchDueWaMessageSchedules, command ini cuma
 * MENGKLAIM pekerjaan (lewat App\Models\JadwalKelasReminderLog, race-
 * safe dengan pola locked-lookup + catch QueryException yang sama
 * persis) -- App\Jobs\SendJadwalReminder yang benar-benar mengirim,
 * termasuk guard kategori package Chat/WhatsApp-nya sendiri.
 *
 * Update 7 September 2026 (permintaan user: "kita siapin fitur biar
 * admin set sendiri mngkn mau ditambahkan 1 hari sblmnya 6 jam
 * sblmnya") -- SEBELUMNYA satu company cuma punya SATU jendela
 * (`$setting->remindMinutesBefore()`, method itu SUDAH DIHAPUS).
 * SEKARANG loop `$setting->rules()` -- satu Jadwal Kelas due bisa
 * diklaim & dikirimi pengingat BEBERAPA KALI, satu kali PER rule yang
 * jendelanya sudah masuk (klaim keyed per (jadwal_kelas_id,
 * jadwal_reminder_rule_id), lihat App\Models\JadwalKelasReminderLog &
 * migration add_reminder_rule_to_jadwal_kelas_reminder_logs_table.php).
 * Company tanpa rule sama sekali (belum pernah isi pengaturan) otomatis
 * tidak dapat pengingat -- tidak ada fallback ke jendela default,
 * konsisten dengan perilaku lama (setting yang remind_value-nya kosong
 * juga tidak pernah mengirim apa-apa).
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
            ->with('rules')
            ->chunkById(50, function ($settings) use ($now, &$count) {
                foreach ($settings as $setting) {
                    foreach ($setting->rules as $rule) {
                        $dueBefore = $now->copy()->addMinutes($rule->minutesBefore());

                        $due = JadwalKelas::query()
                            ->where('company_id', $setting->company_id)
                            ->where('status', JadwalKelas::STATUS_ACTIVE)
                            ->whereNotNull('start_time')
                            ->where('start_time', '>', $now)
                            ->where('start_time', '<=', $dueBefore)
                            ->whereDoesntHave('reminderLogs', function ($q) use ($rule) {
                                $q->where('jadwal_reminder_rule_id', $rule->id);
                            })
                            ->get(['id', 'company_id']);

                        foreach ($due as $kelas) {
                            $count += $this->claimAndDispatch($kelas->id, $kelas->company_id, $rule->id);
                        }
                    }
                }
            });

        $this->info("Dispatched {$count} due Jadwal reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Race-safe claim -- pola identik App\Console\Commands\
     * DispatchDueWaMessageSchedules::claimAndDispatch() (lihat
     * docblock-nya). Update 7 September 2026: kunci klaimnya sekarang
     * komposit (jadwal_kelas_id, jadwal_reminder_rule_id) -- lock/lookup
     * & unique constraint DB (jkrl_kelas_rule_unique) keduanya mengikuti
     * pasangan ini, jadi rule yang berbeda untuk Jadwal Kelas yang sama
     * bisa saling klaim tanpa bentrok.
     */
    private function claimAndDispatch(string $jadwalKelasId, string $companyId, string $reminderRuleId): int
    {
        $claimed = DB::transaction(function () use ($jadwalKelasId, $companyId, $reminderRuleId) {
            $find = fn () => JadwalKelasReminderLog::where('jadwal_kelas_id', $jadwalKelasId)
                ->where('jadwal_reminder_rule_id', $reminderRuleId)
                ->lockForUpdate()
                ->first();

            if ($find()) {
                return false;
            }

            try {
                JadwalKelasReminderLog::create([
                    'jadwal_kelas_id' => $jadwalKelasId,
                    'jadwal_reminder_rule_id' => $reminderRuleId,
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

        SendJadwalReminder::dispatch($jadwalKelasId, $reminderRuleId);

        return 1;
    }
}
