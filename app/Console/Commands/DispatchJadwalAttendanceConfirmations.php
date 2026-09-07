<?php

namespace App\Console\Commands;

use App\Jobs\SendJadwalAttendanceConfirmation;
use App\Models\JadwalAttendanceConfirmationLog;
use App\Models\JadwalKelas;
use App\Models\JadwalReminderSetting;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Fitur konfirmasi kehadiran otomatis (permintaan user: "5 menit
 * setelah selesai jam pelajaran, sistem kirim WA ke pengajar apakah
 * murid hadir, kalau hadir tanya materi apa yang diajarkan, itu jadi
 * history murid"). Dijadwalkan tiap 5 menit (lihat bootstrap/app.php)
 * -- mencari App\Models\JadwalKelas yang jam SELESAI-nya sudah lewat
 * minimal 5 menit (tapi tidak lebih dari 35 menit, jendela tangkap
 * ulang kalau satu-dua tick sempat tertunda/terlewat -- lihat App\
 * Console\Commands\DispatchDueJadwalReminders's docblock untuk pola
 * jendela serupa), belum diabsen sama sekali, milik company yang
 * mengaktifkan `attendance_confirmation_enabled` di Pengaturan
 * Pengingat (App\Models\JadwalReminderSetting).
 *
 * Sama seperti App\Console\Commands\DispatchDueJadwalReminders /
 * DispatchJadwalPengajarDailyReminders, command ini cuma MENGKLAIM
 * pekerjaan (lewat App\Models\JadwalAttendanceConfirmationLog, race-
 * safe dengan pola locked-lookup + catch QueryException yang sama
 * persis) -- App\Jobs\SendJadwalAttendanceConfirmation yang benar-
 * benar memulai sesinya, termasuk guard kategori package Chat/WhatsApp
 * miliknya sendiri.
 */
class DispatchJadwalAttendanceConfirmations extends Command
{
    protected $signature = 'jadwal:dispatch-attendance-confirmations';

    protected $description = 'Enqueue sesi konfirmasi kehadiran WA ke pengajar untuk sesi Jadwal Kelas yang baru selesai, sesuai pengaturan tiap company';

    public function handle(): int
    {
        $now = now();
        $windowStart = $now->copy()->subMinutes(35);
        $windowEnd = $now->copy()->subMinutes(5);
        $count = 0;

        JadwalReminderSetting::query()
            ->where('enabled', true)
            ->where('attendance_confirmation_enabled', true)
            ->whereNotNull('device_id')
            ->chunkById(50, function ($settings) use ($windowStart, $windowEnd, &$count) {
                foreach ($settings as $setting) {
                    $due = JadwalKelas::query()
                        ->where('company_id', $setting->company_id)
                        ->where('status', JadwalKelas::STATUS_ACTIVE)
                        ->whereNotNull('end_time')
                        ->whereNull('attendance_status')
                        ->whereNotNull('student_id')
                        ->whereNotNull('pengajar_id')
                        ->where('end_time', '>', $windowStart)
                        ->where('end_time', '<=', $windowEnd)
                        ->whereDoesntHave('attendanceConfirmationLog')
                        ->get(['id', 'company_id']);

                    foreach ($due as $kelas) {
                        $count += $this->claimAndDispatch($kelas->id, $kelas->company_id);
                    }
                }
            });

        $this->info("Dispatched {$count} konfirmasi kehadiran.");

        return self::SUCCESS;
    }

    /**
     * Race-safe claim -- pola identik App\Console\Commands\
     * DispatchDueJadwalReminders::claimAndDispatch() (lihat
     * docblock-nya).
     */
    private function claimAndDispatch(string $jadwalKelasId, string $companyId): int
    {
        $claimed = DB::transaction(function () use ($jadwalKelasId, $companyId) {
            $find = fn () => JadwalAttendanceConfirmationLog::where('jadwal_kelas_id', $jadwalKelasId)
                ->lockForUpdate()
                ->first();

            if ($find()) {
                return false;
            }

            try {
                JadwalAttendanceConfirmationLog::create([
                    'company_id' => $companyId,
                    'jadwal_kelas_id' => $jadwalKelasId,
                    'status' => JadwalAttendanceConfirmationLog::STATUS_PENDING,
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

        SendJadwalAttendanceConfirmation::dispatch($jadwalKelasId);

        return 1;
    }
}
