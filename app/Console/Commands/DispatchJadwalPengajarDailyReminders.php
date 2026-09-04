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
 * spec poin 9). "Besok" dihitung dari tanggal command ini jalan.
 * Idempotent lewat App\Models\JadwalPengajarReminderLog (unique
 * pengajar_id+reminder_date) dengan pola klaim race-safe yang sama
 * seperti App\Console\Commands\DispatchDueJadwalReminders::
 * claimAndDispatch() -- aman kalau dijalankan berulang untuk tanggal
 * yang sama.
 *
 * Update 7 September 2026 (permintaan user: "kirim rekap tambahkan
 * jam, jam brp mau dikirim ketika di slide tampilkan jam brp mau
 * dikirim") -- SEBELUMNYA scheduler (bootstrap/app.php) memanggil
 * command ini SEKALI PERSIS jam 19:00 (`->dailyAt('19:00')`), jam yang
 * SAMA untuk SEMUA company, hardcode. SEKARANG scheduler memanggil
 * command ini tiap 15 menit (`->everyFifteenMinutes()`), dan command
 * INI SENDIRI yang menyaring: cuma company yang jam saat ini (Asia/
 * Jakarta) SUDAH LEWAT `remind_notify_pengajar_time` miliknya (kolom
 * baru di jadwal_reminder_settings, default '19:00' -- SAMA PERSIS
 * dengan hardcode lama, jadi company yang belum sempat mengubahnya
 * tidak berubah perilaku sama sekali) yang diproses tiap run. Log
 * unique (pengajar_id+reminder_date) yang SUDAH ADA sebelumnya yang
 * mencegah rekap terkirim dobel walau command ini jalan berkali-kali
 * sehari setelah jam settingnya lewat -- TIDAK perlu perubahan apa pun
 * di situ.
 */
class DispatchJadwalPengajarDailyReminders extends Command
{
    protected $signature = 'jadwal:dispatch-pengajar-reminders';

    protected $description = 'Enqueue rekap WA H-1 ke tiap pengajar yang punya sesi aktif besok, sesuai jam kirim (remind_notify_pengajar_time) masing-masing company';

    public function handle(): int
    {
        $now = now();
        $currentTime = $now->format('H:i');
        $tomorrow = $now->copy()->addDay()->toDateString();
        $count = 0;

        JadwalReminderSetting::query()
            ->where('enabled', true)
            ->where('remind_notify_pengajar', true)
            ->whereNotNull('device_id')
            // String 'H:i' zero-padded ("06:00".."23:59") jadi
            // perbandingan lexicographic ini setara perbandingan waktu --
            // lihat docblock migration add_pengajar_reminder_time_to_....php
            // kenapa kolomnya string, bukan tipe `time`.
            ->where('remind_notify_pengajar_time', '<=', $currentTime)
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
