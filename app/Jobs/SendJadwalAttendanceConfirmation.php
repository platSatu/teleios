<?php

namespace App\Jobs;

use App\Jobs\Concerns\NormalizesWhatsAppJid;
use App\Models\JadwalAttendanceConfirmationLog;
use App\Models\JadwalKelas;
use App\Models\JadwalReminderSetting;
use App\Services\Chat\ChatbotFlowService;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\Jadwal\AttendanceConfirmationFlowProvisioner;
use App\Services\PackageLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Memulai SATU sesi konfirmasi kehadiran WA ke pengajar untuk satu
 * App\Models\JadwalKelas -- dispatch oleh App\Console\Commands\
 * DispatchJadwalAttendanceConfirmations setelah baris App\Models\
 * JadwalAttendanceConfirmationLog-nya berhasil diklaim ('pending').
 * Mengikuti pola guard yang sama seperti App\Jobs\SendJadwalReminder /
 * SendJadwalPengajarReminder (lihat docblock keduanya) -- disederhanakan
 * jadi hanya SATU penerima (pengajar sesi ini), tidak ada template
 * pesan (teks pertanyaan + opsi datang dari flow-nya sendiri, lihat
 * App\Services\Jadwal\AttendanceConfirmationFlowProvisioner).
 *
 * BEDA dari kedua job di atas: ini TIDAK mengirim satu pesan lalu
 * selesai -- ini MEMULAI sesi App\Services\Chat\ChatbotFlowService
 * (App\Models\WaChatbotState) yang menunggu balasan pengajar (hadir/
 * tidak hadir/izin, lalu materi kalau hadir). Balasannya nanti
 * ditangkap App\Http\Controllers\Api\WaIncomingMessageWebhookController
 * seperti sesi chatbot flow manapun -- job ini SELESAI begitu pesan
 * pertama terkirim, tidak menunggu balasan apa pun.
 */
class SendJadwalAttendanceConfirmation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, NormalizesWhatsAppJid, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(protected string $jadwalKelasId)
    {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("jadwal-attendance-confirmation-{$this->jadwalKelasId}"))
                ->releaseAfter(120)
                ->expireAfter(180),
        ];
    }

    public function handle(
        SystemJwtService $jwtService,
        InboxService $inbox,
        PackageLimitService $packageLimits,
        AttendanceConfirmationFlowProvisioner $provisioner,
        ChatbotFlowService $chatbotFlow,
    ): void {
        $log = $this->findLog();

        if (! $log || $log->status === JadwalAttendanceConfirmationLog::STATUS_SENT) {
            return;
        }

        $kelas = JadwalKelas::with(['company.user', 'pengajar', 'mataPelajaran', 'student'])->find($this->jadwalKelasId);

        if (! $kelas) {
            $this->skip($log, 'Jadwal Kelas sudah dihapus.');

            return;
        }

        // Dicek ulang di sini (bukan cuma di query command) -- bisa saja
        // sempat diabsen manual, atau dinonaktifkan, di antara command
        // mengklaim baris log ini dan job ini benar-benar jalan.
        if ($kelas->status !== JadwalKelas::STATUS_ACTIVE) {
            $this->skip($log, 'Jadwal Kelas ini sudah tidak aktif.');

            return;
        }

        if ($kelas->attendance_status !== null) {
            $this->skip($log, 'Absensi sesi ini sudah tercatat (kemungkinan diisi manual).');

            return;
        }

        if (! $kelas->student_id) {
            $this->skip($log, 'Sesi ini tidak punya murid terpasang, tidak ada yang perlu diabsen.');

            return;
        }

        $company = $kelas->company;

        if (! $company) {
            $this->skip($log, 'Company pemilik Jadwal Kelas ini tidak valid.');

            return;
        }

        if (! $packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES)) {
            $this->skip($log, 'Company tidak memiliki package aktif kategori Chat/WhatsApp -- konfirmasi kehadiran tidak dikirim.');

            return;
        }

        $setting = JadwalReminderSetting::where('company_id', $company->id)->first();

        if (! $setting || ! $setting->enabled || ! $setting->attendance_confirmation_enabled || ! $setting->device_id) {
            $this->skip($log, 'Konfirmasi kehadiran otomatis belum diaktifkan atau device pengirim belum dipilih.');

            return;
        }

        $owner = $company->user;

        if (! $owner) {
            $this->skip($log, 'Company tidak memiliki user pemilik yang valid.');

            return;
        }

        $pengajar = $kelas->pengajar;

        if (! $pengajar || ! $pengajar->handphone) {
            $this->skip($log, 'Pengajar tidak ditemukan atau belum punya nomor HP.');

            return;
        }

        $jid = $this->toIndividualJid($pengajar->handphone);

        if (! $jid) {
            $this->skip($log, 'Nomor HP pengajar tidak valid.');

            return;
        }

        try {
            $flow = $provisioner->ensureFlowFor($setting, $company);

            $result = $chatbotFlow->start(
                $flow,
                $setting->device_id,
                $jid,
                null,
                ['jadwal_kelas_id' => $kelas->id],
                $this->composeFirstMessage($kelas),
            );

            $token = $jwtService->mintFor($owner);
            $lastMessageId = null;

            foreach ($result['messages'] as $body) {
                if ($body === '') {
                    continue;
                }

                $sent = $inbox->send($token, $setting->device_id, $jid, $body);
                $lastMessageId = $sent['message_id'] ?? $lastMessageId;
            }

            $log->forceFill([
                'pengajar_id' => $pengajar->id,
                'status' => JadwalAttendanceConfirmationLog::STATUS_SENT,
                'message_id' => $lastMessageId,
                'sent_at' => now(),
                'attempts' => $log->attempts + 1,
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('SendJadwalAttendanceConfirmation: gagal mengirim', [
                'jadwal_kelas_id' => $this->jadwalKelasId,
                'error' => $e->getMessage(),
            ]);

            $log->forceFill(['attempts' => $log->attempts + 1])->save();

            // Biarkan naik supaya mekanisme retry queue ($tries/$backoff
            // di atas) jalan -- failed() di bawah baru menandai 'failed'
            // kalau semua percobaan habis.
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $this->findLog()?->forceFill([
            'status' => JadwalAttendanceConfirmationLog::STATUS_FAILED,
            'error' => $e->getMessage(),
        ])->save();
    }

    protected function findLog(): ?JadwalAttendanceConfirmationLog
    {
        return JadwalAttendanceConfirmationLog::where('jadwal_kelas_id', $this->jadwalKelasId)->first();
    }

    protected function skip(JadwalAttendanceConfirmationLog $log, string $reason): void
    {
        $log->forceFill([
            'status' => JadwalAttendanceConfirmationLog::STATUS_SKIPPED,
            'error' => $reason,
            'attempts' => $log->attempts + 1,
        ])->save();
    }

    /**
     * Pesan pembuka SESI-SPESIFIK (sebut nama murid & jam), dipakai
     * sebagai $firstMessageOverride ke App\Services\Chat\
     * ChatbotFlowService::start() -- lihat docblock method itu. Teks
     * step 'choice' bawaan di flow-nya sendiri (App\Services\Jadwal\
     * AttendanceConfirmationFlowProvisioner) cuma fallback generik,
     * TIDAK PERNAH benar-benar terkirim selama override ini ada.
     */
    protected function composeFirstMessage(JadwalKelas $kelas): string
    {
        $tags = [
            '{{nama_murid}}' => $kelas->student?->name ?? '-',
            '{{nama_pengajar}}' => $kelas->pengajar?->name ?? '-',
            '{{mata_pelajaran}}' => $kelas->mataPelajaran?->name ?? '-',
            '{{jam_mulai}}' => $kelas->start_time?->format('H:i') ?? '-',
            '{{jam_selesai}}' => $kelas->end_time?->format('H:i') ?? '-',
        ];

        return strtr(
            'Halo {{nama_pengajar}}, sesi {{mata_pelajaran}} dengan {{nama_murid}} pukul {{jam_mulai}}-{{jam_selesai}} tadi sudah selesai. Apakah {{nama_murid}} hadir pada sesi tersebut?',
            $tags
        );
    }
}
