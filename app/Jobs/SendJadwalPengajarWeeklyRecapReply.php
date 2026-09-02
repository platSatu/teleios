<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\JadwalReminderSetting;
use App\Models\User;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\Jadwal\JadwalPengajarRecapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Balas rekap jadwal MINGGU INI ke pengajar yang barusan mengetik kata
 * kunci request (Jadwal v2, CLAUDE.md item #15 spec poin 9) --
 * dispatch oleh App\Http\Controllers\Api\
 * WaIncomingMessageWebhookController::tryJadwalPengajarKeyword() SETELAH
 * nomor pengirim berhasil dicocokkan ke salah satu users.handphone
 * company tersebut. Sengaja balas ke $chatJid yang sama persis dengan
 * yang mengirim (bukan cari ulang JID dari nomor pengajar) -- reply
 * langsung ke percakapan yang bertanya, konsisten dengan pola
 * App\Jobs\SendOptOutConfirmationMessage.
 *
 * TIDAK memakai App\Models\JadwalPengajarReminderLog (log itu punya arti
 * "sudah dikirim H-1 buat tanggal X", request on-demand ini beda
 * konteks sepenuhnya) -- kirim langsung tanpa klaim/log, sama seperti
 * SendOptOutConfirmationMessage.
 */
class SendJadwalPengajarWeeklyRecapReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    public function __construct(
        protected string $companyId,
        protected string $deviceId,
        protected string $chatJid,
        protected string $pengajarId,
    ) {
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox, JadwalPengajarRecapService $recap): void
    {
        $company = Company::with('user')->find($this->companyId);
        $pengajar = User::find($this->pengajarId);

        if (! $company || ! $company->user || ! $pengajar) {
            Log::warning('SendJadwalPengajarWeeklyRecapReply: company/pengajar tidak valid', [
                'company_id' => $this->companyId,
                'pengajar_id' => $this->pengajarId,
            ]);

            return;
        }

        $setting = JadwalReminderSetting::where('company_id', $company->id)->first();

        $from = Carbon::now()->startOfWeek();
        $to = Carbon::now()->endOfWeek();

        $sesi = $recap->sesiForRange($company->id, $pengajar->id, $from, $to);

        $body = $recap->composeWeeklyMessage($pengajar, $sesi, $from, $to, $company->name, $setting?->waMessageTemplatePengajar);

        try {
            $token = $jwtService->mintFor($company->user);
            $inbox->send($token, $this->deviceId, $this->chatJid, $body);
        } catch (Throwable $e) {
            Log::warning('SendJadwalPengajarWeeklyRecapReply: gagal mengirim', [
                'pengajar_id' => $this->pengajarId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
