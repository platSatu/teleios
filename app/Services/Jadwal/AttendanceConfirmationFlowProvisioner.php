<?php

namespace App\Services\Jadwal;

use App\Models\Company;
use App\Models\JadwalKelas;
use App\Models\JadwalReminderSetting;
use App\Models\WaChatbotFlow;
use App\Models\WaChatbotFlowStep;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Membangun (dan menjaga tetap utuh) SATU App\Models\WaChatbotFlow
 * "sistem" per company untuk fitur konfirmasi kehadiran otomatis
 * (permintaan user: "5 menit setelah selesai jam pelajaran, sistem
 * kirim WA ke pengajar apakah murid hadir, kalau hadir tanya materi
 * apa yang diajarkan, itu jadi history murid"). Dipakai dari App\Jobs\
 * SendJadwalAttendanceConfirmation SEBELUM memulai sesi lewat App\
 * Services\Chat\ChatbotFlowService::start().
 *
 * BEDA dari flow biasa yang admin buat sendiri lewat flow builder
 * (resources/views/chat/chatbot-flows/index.blade.php): flow ini
 * `trigger_keyword`-nya SENGAJA null (lihat App\Models\WaChatbotFlow::
 * matchesTrigger(), otomatis tidak pernah cocok apa pun) karena sesi
 * ini SELALU dimulai PROAKTIF lewat start()'s $variables/
 * $firstMessageOverride, tidak pernah lewat customer mengetik sesuatu
 * duluan. Admin TETAP BISA membukanya di flow builder (mis. mengubah
 * teks step-nya) kalau mau -- flow ini baris biasa di tabel yang sama,
 * cuma dibuat programatik, bukan disembunyikan.
 *
 * Bentuk flow-nya TETAP -- persis 3 step (lihat App\Services\Chat\
 * ChatbotFlowService::saveJadwalAttendance() yang mengasumsikan bentuk
 * ini lewat step_type, bukan id):
 *   1. choice (is_start) -- "Hadir" / "Tidak Hadir" / "Izin", opsi
 *      "Hadir" bercabang ke step 2, dua lainnya langsung ke step 3.
 *   2. message -- "Materi apa yang diajarkan pada sesi tadi?"
 *      (cuma dilewati jalur "Hadir").
 *   3. action (save_jadwal_attendance) -- menyimpan balik ke
 *      App\Models\JadwalKelas, mengakhiri sesi.
 *
 * Idempotent lewat App\Models\JadwalReminderSetting::
 * attendance_confirmation_flow_id -- flow yang sudah tercatat DAN
 * masih punya persis 3 step dipakai ulang apa adanya (supaya
 * perubahan teks manual admin di builder tidak ditimpa ulang tiap
 * kali); kalau id-nya kosong, flow-nya sudah terhapus, atau jumlah
 * step-nya sudah tidak 3 (rusak/setengah jadi), dibangun ulang dari
 * nol.
 */
class AttendanceConfirmationFlowProvisioner
{
    public const FLOW_NAME = 'Konfirmasi Kehadiran Otomatis (Sistem)';

    /**
     * Sesi menunggu balasan pengajar sampai berapa lama sebelum
     * dianggap timeout & dibersihkan (lihat App\Services\Chat\
     * ChatbotFlowService::activeState()) -- permintaan user: kalau
     * pengajar tidak membalas sama sekali, cukup dibiarkan timeout,
     * tidak perlu kirim ulang. 4 jam dipilih supaya pengajar yang baru
     * sempat cek HP beberapa jam kemudian (mis. sore/malam) masih bisa
     * menjawab, tanpa sesi "menggantung" berhari-hari.
     */
    private const SESSION_TIMEOUT_MINUTES = 240;

    private const REQUIRED_STEP_COUNT = 3;

    public function ensureFlowFor(JadwalReminderSetting $setting, Company $company): WaChatbotFlow
    {
        $existing = $this->findUsableFlow($setting);

        if ($existing) {
            return $existing;
        }

        // Beberapa App\Jobs\SendJadwalAttendanceConfirmation untuk
        // company yang SAMA bisa jalan berbarengan di worker berbeda
        // (mis. beberapa sesi selesai dalam window 5 menit yang sama) --
        // tanpa lock ini, dua job bisa lolos cek "belum ada flow" di
        // atas sekaligus dan masing-masing membuat flow+step sendiri,
        // salah satunya jadi orphan begitu $setting->update() kedua
        // menimpa yang pertama. Pola sama seperti App\Services\Chat\
        // ChatbotFlowService::start()'s start-lock.
        return Cache::lock("attendance-confirmation-flow-provision:{$setting->company_id}", 10)->block(5, function () use ($setting, $company) {
            // Re-check di dalam lock -- job lain yang menang race di atas
            // mungkin sudah selesai membuatnya selagi job ini menunggu.
            $existing = $this->findUsableFlow($setting->fresh());

            if ($existing) {
                return $existing;
            }

            return $this->buildFlow($setting, $company);
        });
    }

    /**
     * Flow yang sudah tercatat di $setting DAN masih punya bentuk yang
     * benar (persis 3 step, lihat class docblock) -- device_id-nya
     * disamakan dulu kalau admin sempat ganti device pengirim (lihat
     * catatan di ensureFlowFor()). Null kalau belum pernah dibuat, baris
     * flow-nya sudah terhapus, atau rusak/setengah jadi.
     */
    private function findUsableFlow(JadwalReminderSetting $setting): ?WaChatbotFlow
    {
        $existing = $setting->attendance_confirmation_flow_id
            ? WaChatbotFlow::find($setting->attendance_confirmation_flow_id)
            : null;

        if (! $existing || $existing->steps()->count() !== self::REQUIRED_STEP_COUNT) {
            return null;
        }

        if ($existing->device_id !== $setting->device_id) {
            $existing->update(['device_id' => $setting->device_id]);
        }

        return $existing;
    }

    private function buildFlow(JadwalReminderSetting $setting, Company $company): WaChatbotFlow
    {
        $existing = $setting->attendance_confirmation_flow_id
            ? WaChatbotFlow::find($setting->attendance_confirmation_flow_id)
            : null;

        return DB::transaction(function () use ($existing, $setting, $company) {
            // Baris lama yang setengah jadi/rusak (mis. sempat gagal di
            // tengah jalan) dibuang dulu -- steps() ikut terhapus lewat
            // cascadeOnDelete() pada wa_chatbot_flow_steps.wa_chatbot_flow_id.
            $existing?->delete();

            $flow = WaChatbotFlow::create([
                'company_id' => $company->id,
                'device_id' => $setting->device_id,
                'name' => self::FLOW_NAME,
                'trigger_keyword' => null,
                'status' => WaChatbotFlow::STATUS_ACTIVE,
                'session_timeout_minutes' => self::SESSION_TIMEOUT_MINUTES,
            ]);

            $actionStep = WaChatbotFlowStep::create([
                'wa_chatbot_flow_id' => $flow->id,
                'step_type' => WaChatbotFlowStep::TYPE_ACTION,
                'message' => 'Baik, terima kasih! Absensi sudah tercatat.',
                'action' => WaChatbotFlowStep::ACTION_SAVE_JADWAL_ATTENDANCE,
                'is_start' => false,
                'position' => 3,
            ]);

            $materiStep = WaChatbotFlowStep::create([
                'wa_chatbot_flow_id' => $flow->id,
                'step_type' => WaChatbotFlowStep::TYPE_MESSAGE,
                'message' => 'Materi apa yang diajarkan pada sesi tadi?',
                'default_next_step_id' => $actionStep->id,
                'is_start' => false,
                'position' => 2,
            ]);

            WaChatbotFlowStep::create([
                'wa_chatbot_flow_id' => $flow->id,
                'step_type' => WaChatbotFlowStep::TYPE_CHOICE,
                'message' => 'Apakah murid hadir pada sesi ini?',
                'options' => [
                    ['label' => 'Hadir', 'value' => JadwalKelas::ATTENDANCE_HADIR, 'next_step_id' => $materiStep->id],
                    ['label' => 'Tidak Hadir', 'value' => JadwalKelas::ATTENDANCE_TIDAK_HADIR, 'next_step_id' => $actionStep->id],
                    ['label' => 'Izin', 'value' => JadwalKelas::ATTENDANCE_IZIN, 'next_step_id' => $actionStep->id],
                ],
                'is_start' => true,
                'position' => 1,
            ]);

            $setting->update(['attendance_confirmation_flow_id' => $flow->id]);

            return $flow;
        });
    }
}
