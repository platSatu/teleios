<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaApiKey;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The ONE thing a third party can do with a WaApiKey token/secret right
 * now: send a WhatsApp message through that specific device — e.g. as a
 * notification channel from another system (an order placed, a ticket
 * updated, whatever the third party wants to alert someone about).
 *
 * Deliberately minimal — no read access to chat history, no device
 * management, nothing beyond "send". Every other Chat feature in this
 * app (auto-reply, scheduled messages, etc.) is reachable only by a
 * logged-in company member; this is the one exception, gated purely by
 * App\Http\Middleware\VerifyWaApiKey (see routes/api.php — no `auth`
 * middleware at all, since the caller isn't a user of this app).
 *
 * Sends through the SAME path every other outbound message in this app
 * uses (App\Jobs\SendAutoReplyMessage, App\Jobs\SendScheduledWaMessage):
 * mint a short-lived system JWT for the company owner (App\Services\
 * Chat\SystemJwtService), then App\Services\Chat\InboxService::send().
 * Go's own per-request AssertOwnership(userID, deviceID) still applies
 * normally — this can't be used to send through a device the key's
 * company doesn't actually own.
 */
class WaApiSendMessageController extends Controller
{
    public function send(Request $request, SystemJwtService $jwtService, InboxService $inbox): JsonResponse
    {
        /** @var WaApiKey $apiKey */
        $apiKey = $request->attributes->get('waApiKey');

        $validated = $request->validate([
            // Plain phone number (e.g. "6281234567890") OR a full WA JID
            // ("6281234567890@s.whatsapp.net" / "...@g.us" for a group) —
            // either is accepted, same flexibility InboxController gives
            // a logged-in user, so a third party doesn't need to know
            // this app's JID format just to send a DM.
            'to' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:4096'],
        ]);

        $chatJid = $this->normalizeJid($validated['to']);

        // Dicatat SEBELUM proses kirim dimulai (bukan cuma pas gagal) — supaya
        // ada jejak "request ini memang sampai & lolos otentikasi WaApiKey"
        // yang bisa dicek terpisah dari apakah pengirimannya sendiri berhasil.
        // Isi pesan sengaja tidak ikut dicatat (cuma panjangnya), konsisten
        // dengan aturan proyek untuk tidak melog data yang tidak perlu.
        Log::info('WaApiSendMessageController: send attempt', [
            'api_key_id' => $apiKey->id,
            'company_id' => $apiKey->company_id,
            'device_id' => $apiKey->device_id,
            'to' => $chatJid,
            'message_length' => mb_strlen($validated['message']),
        ]);

        $owner = $apiKey->company?->user;

        if (! $owner) {
            Log::error('WaApiSendMessageController: company pemilik API Key tidak punya user pemilik yang valid', [
                'api_key_id' => $apiKey->id,
                'company_id' => $apiKey->company_id,
            ]);

            return response()->json([
                'error' => 'Company pemilik API Key ini tidak memiliki user pemilik yang valid.',
            ], 500);
        }

        try {
            $token = $jwtService->mintFor($owner);
            $result = $inbox->send($token, $apiKey->device_id, $chatJid, $validated['message']);

            Log::info('WaApiSendMessageController: send success', [
                'api_key_id' => $apiKey->id,
                'device_id' => $apiKey->device_id,
                'to' => $chatJid,
                'message_id' => $result['id'] ?? null,
                'sent_at' => $result['sent_at'] ?? null,
            ]);

            return response()->json([
                'status' => 'sent',
                'message' => $result,
            ]);
        } catch (Throwable $e) {
            $reason = $this->describeSendFailure($e);

            Log::warning('WaApiSendMessageController: send failed', [
                'api_key_id' => $apiKey->id,
                'company_id' => $apiKey->company_id,
                'device_id' => $apiKey->device_id,
                'to' => $chatJid,
                'error' => $e->getMessage(),
                'reason' => $reason,
            ]);

            return response()->json([
                'error' => $reason,
            ], 502);
        }
    }

    /**
     * Menerjemahkan exception mentah dari InboxService::send() jadi pesan yang
     * bisa langsung ditindaklanjuti pemilik API Key — sebelum ini SELALU
     * kalimat generik "Pastikan device masih terhubung." apa pun sebab
     * aslinya, sehingga "device sudah dihapus" tidak bisa dibedakan dari
     * "device cuma lagi terputus sebentar" di sisi pemanggil (mis. InaStudy).
     *
     * Pola & regex parsing-nya SAMA dengan App\Services\Chat\InboxService::
     * describeSendFailure() dan GoogleFormWebhookController::describeSendFailure()
     * — App\Services\Chat\InboxService::request() membungkus body error JSON
     * asli dari backend Go langsung ke pesan RuntimeException-nya ("...failed:
     * {"error":"..."}"), jadi ini menarik lagi key `error`-nya kalau ada.
     * Kalimatnya sengaja ditulis ulang (bukan panggil versi InboxService/
     * GoogleForm apa adanya) supaya pas untuk konsumen API pihak ketiga —
     * tidak menyebut istilah internal seperti "jadwal" atau "integrasi" yang
     * tidak relevan buat mereka.
     */
    private function describeSendFailure(Throwable $e): string
    {
        $reason = null;

        if (preg_match('/\{.*\}\s*$/s', $e->getMessage(), $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded) && ! empty($decoded['error'])) {
                $reason = (string) $decoded['error'];
            }
        }

        if ($reason === null) {
            return 'Gagal mengirim pesan. Pastikan device masih terhubung.';
        }

        if (str_contains($reason, 'device not found')) {
            return 'Device untuk API Key ini tidak ditemukan (mungkin sudah dihapus). Generate ulang API Key dari device yang masih aktif di menu Connect Device.';
        }

        if (str_contains($reason, 'not connected')) {
            return 'Device untuk API Key ini sedang tidak terhubung ke WhatsApp. Buka menu Connect Device, klik "Reconnect" pada device tersebut, lalu coba kirim ulang.';
        }

        return "Gagal mengirim pesan: {$reason}.";
    }

    /**
     * A bare phone number becomes an individual JID; anything already
     * containing '@' (a real JID, individual or group) is passed through
     * untouched. The digit-normalization itself (stray formatting
     * stripped, plus the Indonesian "0812..."/"812..." -> "6281..."
     * country-code correction) delegates to App\Support\PhoneNumber::
     * normalize() — the same single source of truth
     * App\Jobs\Concerns\NormalizesWhatsAppJid and
     * App\Http\Controllers\Api\GoogleFormWebhookController use — so a
     * third party posting "081234..." reaches the same recipient a
     * manually entered "081234..." Buku Telepon/Kontak number would.
     */
    private function normalizeJid(string $to): string
    {
        if (str_contains($to, '@')) {
            return $to;
        }

        return PhoneNumber::normalize($to).'@s.whatsapp.net';
    }
}
