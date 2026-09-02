<?php

namespace App\Jobs;

use App\Jobs\Concerns\NormalizesWhatsAppJid;
use App\Models\Company;
use App\Models\JadwalPengajarReminderLog;
use App\Models\JadwalReminderSetting;
use App\Models\User;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\Jadwal\JadwalPengajarRecapService;
use App\Services\PackageLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim REKAP WA (bukan per-sesi) untuk SATU pengajar pada SATU
 * tanggal -- dipakai dari 2 jalur (Jadwal v2, CLAUDE.md item #15 spec
 * poin 9 & 10): App\Console\Commands\DispatchJadwalPengajarDailyReminders
 * (H-1 otomatis, sudah lewat klaim App\Models\JadwalPengajarReminderLog)
 * dan App\Http\Controllers\Jadwal\JadwalKelasController::
 * sendPengajarReminder() (manual by admin, kirim ulang eksplisit --
 * lihat $forceResend). Sengaja bukan App\Jobs\SendJadwalReminder yang
 * sudah ada (itu satu pesan PER SESI ke orang tua/murid) -- pengajar
 * butuh SATU pesan rekap semua sesi hari itu (spec poin 9: "jam berapa
 * sampai jam berapa, murid siapa saja").
 */
class SendJadwalPengajarReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, NormalizesWhatsAppJid, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        protected string $companyId,
        protected string $pengajarId,
        protected string $date,
        protected bool $forceResend = false,
    ) {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("jadwal-pengajar-reminder-{$this->pengajarId}-{$this->date}"))
                ->releaseAfter(120)
                ->expireAfter(180),
        ];
    }

    public function handle(
        SystemJwtService $jwtService,
        InboxService $inbox,
        PackageLimitService $packageLimits,
        JadwalPengajarRecapService $recap,
    ): void {
        $log = $this->findOrCreateLog();

        if (! $this->forceResend && $log->status === JadwalPengajarReminderLog::STATUS_SENT) {
            return;
        }

        $company = Company::with('user')->find($this->companyId);

        if (! $company || ! $company->user) {
            $this->skip($log, 'Company atau owner tidak valid.');

            return;
        }

        if (! $packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES)) {
            $this->skip($log, 'Company tidak memiliki package aktif kategori Chat/WhatsApp.');

            return;
        }

        $setting = JadwalReminderSetting::where('company_id', $company->id)->first();

        if (! $setting || ! $setting->enabled || ! $setting->device_id) {
            $this->skip($log, 'Pengaturan pengingat Jadwal belum aktif atau device pengirim belum dipilih.');

            return;
        }

        if (! $this->forceResend && ! $setting->remind_notify_pengajar) {
            $this->skip($log, 'Reminder otomatis ke pengajar belum diaktifkan di pengaturan.');

            return;
        }

        $pengajar = User::find($this->pengajarId);

        if (! $pengajar || ! $pengajar->handphone) {
            $this->skip($log, 'Pengajar tidak ditemukan atau belum punya nomor HP.');

            return;
        }

        $jid = $this->toIndividualJid($pengajar->handphone);

        if (! $jid) {
            $this->skip($log, 'Nomor HP pengajar tidak valid.');

            return;
        }

        $date = Carbon::parse($this->date);
        $sesi = $recap->sesiForRange($company->id, $pengajar->id, $date, $date);

        if ($sesi->isEmpty()) {
            $this->skip($log, 'Tidak ada sesi untuk pengajar ini pada tanggal tersebut.');

            return;
        }

        $body = $recap->composeDailyMessage($pengajar, $sesi, $date, $company->name, $setting->waMessageTemplatePengajar);

        try {
            $token = $jwtService->mintFor($company->user);
            $sent = $inbox->send($token, $setting->device_id, $jid, $body);

            $log->forceFill([
                'status' => JadwalPengajarReminderLog::STATUS_SENT,
                'message_id' => $sent['message_id'] ?? null,
                'sent_at' => now(),
                'attempts' => $log->attempts + 1,
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('SendJadwalPengajarReminder: gagal mengirim', [
                'pengajar_id' => $this->pengajarId,
                'date' => $this->date,
                'error' => $e->getMessage(),
            ]);

            $log->forceFill(['attempts' => $log->attempts + 1])->save();

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $this->findOrCreateLog()->forceFill([
            'status' => JadwalPengajarReminderLog::STATUS_FAILED,
            'error' => $e->getMessage(),
        ])->save();
    }

    private function findOrCreateLog(): JadwalPengajarReminderLog
    {
        return JadwalPengajarReminderLog::firstOrCreate(
            ['pengajar_id' => $this->pengajarId, 'reminder_date' => $this->date],
            ['company_id' => $this->companyId, 'status' => JadwalPengajarReminderLog::STATUS_PENDING],
        );
    }

    private function skip(JadwalPengajarReminderLog $log, string $reason): void
    {
        $log->forceFill([
            'status' => JadwalPengajarReminderLog::STATUS_SKIPPED,
            'error' => $reason,
            'attempts' => $log->attempts + 1,
        ])->save();
    }
}
