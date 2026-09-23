<?php

namespace App\Jobs;

use App\Jobs\Concerns\NormalizesWhatsAppJid;
use App\Models\TagihanPenerima;
use App\Services\Chat\DeviceDirectory;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kirim link bayar App\Models\TagihanPenerima otomatis lewat WhatsApp --
 * dipicu App\Http\Controllers\Tagihan\TagihanController::store() untuk
 * tiap penerima yang App\Models\TagihanPelanggan-nya punya
 * kirim_link_otomatis=true (23 September 2026 redesign poin 2).
 *
 * Queued (bukan inline di controller) supaya request "Buat Tagihan"
 * tidak nunggu HTTP round-trip ke g_backend per pelanggan -- sama alasan
 * App\Jobs\SendScheduledWaMessage dipisah dari controller-nya. Device
 * dipilih dinamis SAAT job jalan (bukan disimpan di Tagihan), sesuai
 * jawaban eksplisit user "diambil dari device atau branch yang sedang
 * konek" -- kalau belum ada device 'connected' di branch itu saat job
 * jalan, dilewati (fail-open, dicatat di log) bukan retry berkali-kali,
 * karena kemungkinan besar memang belum ada HP yang di-scan QR untuk
 * branch itu, bukan gangguan sementara.
 */
class SendTagihanLinkWaMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, NormalizesWhatsAppJid, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public string $tagihanPenerimaId)
    {
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox, DeviceDirectory $deviceDirectory): void
    {
        $penerima = TagihanPenerima::with(['tagihan.company.user', 'tagihan.branchOffice', 'pelanggan'])
            ->find($this->tagihanPenerimaId);

        if (! $penerima || ! $penerima->pelanggan || ! $penerima->pelanggan->kirim_link_otomatis) {
            return;
        }

        $jid = $this->toIndividualJid($penerima->pelanggan->phone_number);

        if (! $jid) {
            Log::info('tagihan-wa-link: pelanggan tidak punya nomor telepon, dilewati', ['tagihan_penerima_id' => $penerima->id]);

            return;
        }

        $branchSlug = $penerima->tagihan?->branchOffice?->slug;
        $owner = $penerima->tagihan?->company?->user;

        if (! $branchSlug || ! $owner) {
            Log::warning('tagihan-wa-link: branch slug atau owner company tidak ditemukan', ['tagihan_penerima_id' => $penerima->id]);

            return;
        }

        $device = $deviceDirectory->devicesForCompany($penerima->company_id, $penerima->branch_office_id)
            ->firstWhere('status', 'connected');

        if (! $device) {
            Log::info('tagihan-wa-link: tidak ada device WhatsApp yang terhubung di branch ini, dilewati', [
                'tagihan_penerima_id' => $penerima->id,
                'branch_office_id' => $penerima->branch_office_id,
            ]);

            return;
        }

        $link = route('tagihan.public.show', ['branchSlug' => $branchSlug, 'token' => $penerima->public_token]);
        $message = $penerima->tagihan->renderWaTemplate($penerima->pelanggan->name, $link);

        try {
            $token = $jwtService->mintFor($owner);
            $inbox->send($token, $device->id, $jid, $message, $penerima->tagihan->company, 'broadcast_send');
        } catch (Throwable $e) {
            Log::warning('tagihan-wa-link: gagal mengirim link tagihan', [
                'tagihan_penerima_id' => $penerima->id,
                'error' => InboxService::describeSendFailure($e),
            ]);

            if (! InboxService::isDeviceDisconnected($e)) {
                throw $e;
            }
        }
    }
}
