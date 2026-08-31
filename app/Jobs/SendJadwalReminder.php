<?php

namespace App\Jobs;

use App\Jobs\Concerns\NormalizesWhatsAppJid;
use App\Models\JadwalKelas;
use App\Models\JadwalKelasReminderLog;
use App\Models\JadwalReminderSetting;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
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
 * Mengirim pengingat WA untuk satu App\Models\JadwalKelas -- dispatch
 * oleh App\Console\Commands\DispatchDueJadwalReminders setelah baris
 * App\Models\JadwalKelasReminderLog-nya berhasil diklaim ('pending').
 * Sengaja terpisah TOTAL dari App\Jobs\SendScheduledWaMessage (job/
 * command Chat yang sudah ada) -- tidak berbagi kelas, tidak mengubah
 * perilakunya sedikit pun. Mengikuti pola guard & error-handling yang
 * sama (lihat docblock SendScheduledWaMessage untuk alasan tiap
 * langkah), disederhanakan untuk kasus Jadwal yang lebih sempit: satu
 * penerima per target (bukan daftar penerima broadcast), tidak ada
 * lampiran/media, tidak ada quota/throttle broadcast (pengingat Jadwal
 * tidak dianggap "broadcast_send" -- lihat App\Services\
 * PackageLimitService's docblock soal metric generik itu; kalau nanti
 * perlu quota sendiri, tambahkan LimitMetric baru, jangan pinjam
 * 'broadcast_send' milik Chat).
 */
class SendJadwalReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, NormalizesWhatsAppJid, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(protected string $jadwalKelasId)
    {
    }

    /**
     * Keyed on jadwal_kelas_id saja -- satu Jadwal Kelas cuma pernah
     * punya SATU baris log (unique, lihat migration-nya), jadi tidak
     * perlu komponen lain seperti WaMessageSchedule punya
     * (recipient/day/step) yang memang bisa berulang per hari.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("jadwal-reminder-{$this->jadwalKelasId}"))
                ->releaseAfter(120)
                ->expireAfter(180),
        ];
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox, PackageLimitService $packageLimits): void
    {
        $log = $this->findLog();

        if (! $log || $log->status === JadwalKelasReminderLog::STATUS_SENT) {
            return;
        }

        $kelas = JadwalKelas::with(['company.user', 'pengajar', 'mataPelajaran', 'student'])->find($this->jadwalKelasId);

        if (! $kelas) {
            $this->skip($log, 'Jadwal Kelas sudah dihapus.');

            return;
        }

        $company = $kelas->company;

        if (! $company) {
            $this->skip($log, 'Company pemilik Jadwal Kelas ini tidak valid.');

            return;
        }

        // Guard utama yang menjawab pertanyaan "company ini subscribe
        // layanan Chat/WA atau tidak" -- lihat App\Services\
        // PackageLimitService::hasActiveCategoryPackage()'s docblock.
        // Dicek paling awal, sebelum kerja lain apa pun, sama seperti
        // requireActivePackage() di SendScheduledWaMessage. Company yang
        // cuma subscribe "Jadwal" (tanpa kategori Chat/WhatsApp) berhenti
        // di sini -- dicatat skipped, bukan failed, karena ini memang
        // bukan seharusnya terkirim.
        if (! $packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES)) {
            $this->skip($log, 'Company tidak memiliki package aktif kategori Chat/WhatsApp -- pengingat Jadwal tidak dikirim.');

            return;
        }

        $setting = JadwalReminderSetting::where('company_id', $company->id)->first();

        if (! $setting || ! $setting->enabled || ! $setting->device_id) {
            $this->skip($log, 'Pengaturan pengingat Jadwal belum diaktifkan atau device pengirim belum dipilih.');

            return;
        }

        $owner = $company->user;

        if (! $owner) {
            $this->skip($log, 'Company tidak memiliki user pemilik yang valid.');

            return;
        }

        $recipients = $this->resolveRecipients($kelas, $setting);

        if (empty($recipients)) {
            $this->skip($log, 'Tidak ada nomor HP orang tua/murid yang bisa dihubungi untuk target pengingat yang dipilih.');

            return;
        }

        $body = $this->composeMessage($kelas, $setting);

        try {
            $token = $jwtService->mintFor($owner);
            $lastMessageId = null;
            $anySent = false;

            foreach ($recipients as $phone) {
                $jid = $this->toIndividualJid($phone);

                if (! $jid) {
                    continue;
                }

                $sent = $inbox->send($token, $setting->device_id, $jid, $body);
                $lastMessageId = $sent['message_id'] ?? $lastMessageId;
                $anySent = true;
            }

            if (! $anySent) {
                $this->skip($log, 'Nomor HP yang tersimpan tidak valid untuk dikirimi WhatsApp.');

                return;
            }

            $log->forceFill([
                'status' => JadwalKelasReminderLog::STATUS_SENT,
                'message_id' => $lastMessageId,
                'sent_at' => now(),
                'attempts' => $log->attempts + 1,
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('SendJadwalReminder: gagal mengirim pengingat', [
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
            'status' => JadwalKelasReminderLog::STATUS_FAILED,
            'error' => $e->getMessage(),
        ])->save();
    }

    protected function findLog(): ?JadwalKelasReminderLog
    {
        return JadwalKelasReminderLog::where('jadwal_kelas_id', $this->jadwalKelasId)->first();
    }

    protected function skip(JadwalKelasReminderLog $log, string $reason): void
    {
        $log->forceFill([
            'status' => JadwalKelasReminderLog::STATUS_SKIPPED,
            'error' => $reason,
            'attempts' => $log->attempts + 1,
        ])->save();
    }

    /**
     * @return list<string>
     */
    protected function resolveRecipients(JadwalKelas $kelas, JadwalReminderSetting $setting): array
    {
        $student = $kelas->student;

        if (! $student) {
            return [];
        }

        $numbers = match ($setting->remind_target) {
            JadwalReminderSetting::TARGET_STUDENT => [$student->student_phone_number],
            JadwalReminderSetting::TARGET_BOTH => [$student->parent_phone_number, $student->student_phone_number],
            default => [$student->parent_phone_number], // TARGET_PARENT
        };

        return array_values(array_filter($numbers, fn ($n) => filled($n)));
    }

    protected function composeMessage(JadwalKelas $kelas, JadwalReminderSetting $setting): string
    {
        $template = $setting->wa_message_template_id ? $setting->waMessageTemplate : null;

        $body = ($template && $template->status === 'active' && $template->review_status === 'approved')
            ? $template->composedMessage()
            : $this->defaultMessage();

        $tags = [
            '{{nama_murid}}' => $kelas->student?->name ?? '-',
            '{{nama_pengajar}}' => $kelas->pengajar?->name ?? '-',
            '{{mata_pelajaran}}' => $kelas->mataPelajaran?->name ?? '-',
            '{{tanggal}}' => $kelas->start_time?->translatedFormat('d F Y') ?? '-',
            '{{jam_mulai}}' => $kelas->start_time?->format('H:i') ?? '-',
            '{{jam_selesai}}' => $kelas->end_time?->format('H:i') ?? '-',
            '{{nama_perusahaan}}' => $kelas->company?->name ?? '-',
        ];

        return strtr($body, $tags);
    }

    protected function defaultMessage(): string
    {
        return "Pengingat jadwal kelas:\n"
            ."{{nama_murid}} ada kelas {{mata_pelajaran}} bersama {{nama_pengajar}}\n"
            ."pada {{tanggal}} pukul {{jam_mulai}}.";
    }
}
