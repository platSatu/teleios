<?php

namespace App\Jobs;

use App\Jobs\Concerns\NormalizesWhatsAppJid;
use App\Models\Tagihan;
use App\Models\TagihanPenerima;
use App\Models\TagihanReminderLog;
use App\Models\TagihanReminderRule;
use App\Services\Chat\DeviceDirectory;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengirim pengingat WA untuk satu App\Models\TagihanPenerima -- dispatch
 * oleh App\Console\Commands\DispatchDueTagihanReminders setelah baris
 * App\Models\TagihanReminderLog-nya berhasil diklaim ('pending'). Pola
 * guard & error-handling disamakan dengan App\Jobs\SendJadwalReminder
 * (klaim/skip/failed) digabung dengan cara kirim App\Jobs\
 * SendTagihanLinkWaMessage (device dipilih dinamis per branch, fail-open
 * kalau device belum konek -- bukan retry berkali-kali).
 *
 * 23 September 2026: melengkapi tabel yang sebelumnya sengaja dibiarkan
 * kosong ("jangan sambungkan dulu ya dengan whatsapp", lihat migration
 * create_tagihan_reminder_rule_table.php's docblock) -- instruksi itu
 * sudah dicabut user (audit kesiapan launch).
 *
 * TIDAK digabung dengan SendTagihanLinkWaMessage meski isinya mirip
 * (sama-sama "kirim link bayar Tagihan lewat WA") -- job itu terikat ke
 * momen "Tagihan baru dibuat" (dipicu TagihanController::store(), sekali
 * per penerima, tidak dicatat ke tabel log manapun), job ini terikat ke
 * jadwal H-N sebelum due_date dan WAJIB idempotent lewat
 * TagihanReminderLog supaya rule yang sama tidak pernah mengirim dua
 * kali ke penerima yang sama.
 */
class SendTagihanReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, NormalizesWhatsAppJid, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(protected string $tagihanPenerimaId, protected string $reminderRuleId)
    {
    }

    /**
     * Keyed on (tagihan_penerima_id, reminderRuleId) -- sama alasan
     * persis App\Jobs\SendJadwalReminder::middleware(): supaya rule
     * berbeda pada TagihanPenerima yang sama (mis. H-7 dan H-3 keduanya
     * jatuh due dalam rentang command yang sama) tidak saling menahan.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("tagihan-reminder-{$this->tagihanPenerimaId}-{$this->reminderRuleId}"))
                ->releaseAfter(120)
                ->expireAfter(180),
        ];
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox, DeviceDirectory $deviceDirectory): void
    {
        $log = $this->findLog();

        if (! $log || $log->status === TagihanReminderLog::STATUS_SENT) {
            return;
        }

        $penerima = TagihanPenerima::with(['tagihan.company.user', 'tagihan.branchOffice', 'pelanggan'])
            ->find($this->tagihanPenerimaId);

        if (! $penerima) {
            $this->skip($log, 'Tagihan (penerima) sudah dihapus.');

            return;
        }

        // Race yang wajar: klaim terjadi beberapa menit sebelum job ini
        // benar-benar jalan (lewat antrean) -- kalau pelanggan sempat
        // bayar/invoice-nya dibatalkan/kadaluarsa di rentang waktu itu,
        // pengingat ini sudah tidak relevan lagi. Dicatat skipped, bukan
        // failed -- ini bukan error, memang seharusnya tidak terkirim.
        if ($penerima->status !== TagihanPenerima::STATUS_BELUM_BAYAR) {
            $this->skip($log, 'Status tagihan sudah berubah (bukan lagi belum_bayar) sebelum pengingat sempat terkirim.');

            return;
        }

        $tagihan = $penerima->tagihan;
        $company = $tagihan?->company;
        $owner = $company?->user;
        $branchSlug = $tagihan?->branchOffice?->slug;

        if (! $tagihan || ! $company || ! $owner || ! $branchSlug) {
            $this->skip($log, 'Data Tagihan/company/branch tidak lengkap atau tidak valid.');

            return;
        }

        if (! $penerima->pelanggan) {
            $this->skip($log, 'Data pelanggan penerima tagihan ini sudah dihapus.');

            return;
        }

        $jid = $this->toIndividualJid($penerima->pelanggan->phone_number);

        if (! $jid) {
            $this->skip($log, 'Nomor HP pelanggan tidak ada atau tidak valid.');

            return;
        }

        $device = $deviceDirectory->devicesForCompany($penerima->company_id, $penerima->branch_office_id)
            ->firstWhere('status', 'connected');

        if (! $device) {
            $this->skip($log, 'Tidak ada device WhatsApp yang terhubung di branch ini saat pengingat mau dikirim.');

            return;
        }

        $rule = TagihanReminderRule::find($this->reminderRuleId);
        $link = route('tagihan.public.show', ['branchSlug' => $branchSlug, 'token' => $penerima->public_token]);
        $body = $this->composeMessage($penerima, $tagihan, $rule, $link);

        try {
            $token = $jwtService->mintFor($owner);
            $sent = $inbox->send($token, $device->id, $jid, $body, $company, 'broadcast_send');

            $log->forceFill([
                'status' => TagihanReminderLog::STATUS_SENT,
                'message_id' => $sent['message_id'] ?? null,
                'sent_at' => now(),
                'attempts' => $log->attempts + 1,
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('SendTagihanReminder: gagal mengirim pengingat', [
                'tagihan_penerima_id' => $this->tagihanPenerimaId,
                'reminder_rule_id' => $this->reminderRuleId,
                'error' => InboxService::describeSendFailure($e),
            ]);

            // Device belum/putus koneksi -- fail-open (skip, bukan retry
            // berkali-kali), sama alasan persis SendTagihanLinkWaMessage:
            // kemungkinan besar memang belum ada HP yang di-scan QR untuk
            // branch itu, bukan gangguan sementara yang akan pulih sendiri
            // dalam $backoff di atas.
            if (InboxService::isDeviceDisconnected($e)) {
                $this->skip($log, 'Device WhatsApp terputus saat pengingat dikirim: '.InboxService::describeSendFailure($e));

                return;
            }

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
            'status' => TagihanReminderLog::STATUS_FAILED,
            'error' => $e->getMessage(),
        ])->save();
    }

    protected function findLog(): ?TagihanReminderLog
    {
        return TagihanReminderLog::where('tagihan_penerima_id', $this->tagihanPenerimaId)
            ->where('tagihan_reminder_rule_id', $this->reminderRuleId)
            ->first();
    }

    protected function skip(TagihanReminderLog $log, string $reason): void
    {
        $log->forceFill([
            'status' => TagihanReminderLog::STATUS_SKIPPED,
            'error' => $reason,
            'attempts' => $log->attempts + 1,
        ])->save();
    }

    /**
     * Isi pesan pengingat -- REUSE App\Models\Tagihan::renderWaTemplate()
     * (sudah berisi nominal/jatuh tempo/link, sama seperti pesan "Tagihan
     * baru dibuat" milik SendTagihanLinkWaMessage) ditambah satu baris
     * penanda "ini pengingat" + label rule-nya (App\Models\
     * TagihanReminderRule::label(), mis. "H-7"/"H-1") di atasnya --
     * TIDAK ada field template pesan terpisah khusus reminder di skema
     * (cuma Tagihan::wa_template_message untuk pesan awal), jadi tidak
     * ada yang perlu diisi admin tambahan supaya fitur ini langsung bisa
     * dipakai untuk Tagihan yang sudah ada.
     */
    protected function composeMessage(TagihanPenerima $penerima, Tagihan $tagihan, ?TagihanReminderRule $rule, string $link): string
    {
        $base = $tagihan->renderWaTemplate($penerima->pelanggan->name, $link);
        $label = $rule?->label() ?? 'Pengingat';

        return "🔔 Pengingat ({$label}) -- jatuh tempo sebentar lagi!\n\n".$base;
    }
}
