<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\PackageLimitExceededException;
use App\Http\Controllers\Controller;
use App\Models\WaApiKey;
use App\Models\WaApiRequestLog;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\Chat\WaApiUsageService;
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
    public function send(Request $request, SystemJwtService $jwtService, InboxService $inbox, WaApiUsageService $usage): JsonResponse
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

        // Atribut dasar riwayat request (App\Models\WaApiRequestLog) —
        // dicatat di SETIAP cabang hasil di bawah (terkirim / diblokir
        // paket-kuota / gagal), supaya pemilik API key bisa melihat &
        // menghitung semua pemakaian API-nya dari dashboard maupun lewat
        // GET /wa-api/v1/usage. Isi pesan sengaja tidak disimpan.
        $logAttributes = [
            'recipient' => $chatJid,
            'message_length' => mb_strlen($validated['message']),
            'ip_address' => $request->ip(),
        ];

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

            $usage->record($apiKey, WaApiRequestLog::ENDPOINT_SEND_MESSAGE, WaApiRequestLog::STATUS_FAILED, 500, $logAttributes + [
                'error' => 'Company pemilik API Key tidak memiliki user pemilik yang valid.',
            ]);

            return response()->json([
                'error' => 'Company pemilik API Key ini tidak memiliki user pemilik yang valid.',
            ], 500);
        }

        try {
            $token = $jwtService->mintFor($owner);

            // $apiKey->company passed through so this third-party send
            // finally goes through the same package-active/quota guard
            // every other outbound WA path already has (CLAUDE.md
            // checklist item #3 — this controller was the original gap
            // that started the whole centralization: previously a
            // company whose package had expired, or whose broadcast_send
            // quota was exhausted, could keep sending through this
            // endpoint forever). See InboxService::guardPackageLimit()'s
            // docblock for exactly what this does and doesn't check.
            $result = $inbox->send($token, $apiKey->device_id, $chatJid, $validated['message'], $apiKey->company);

            Log::info('WaApiSendMessageController: send success', [
                'api_key_id' => $apiKey->id,
                'device_id' => $apiKey->device_id,
                'to' => $chatJid,
                'message_id' => $result['id'] ?? null,
                'sent_at' => $result['sent_at'] ?? null,
            ]);

            $usage->record($apiKey, WaApiRequestLog::ENDPOINT_SEND_MESSAGE, WaApiRequestLog::STATUS_SENT, 200, $logAttributes + [
                'wa_message_id' => isset($result['id']) ? (string) $result['id'] : null,
            ]);

            return response()->json([
                'status' => 'sent',
                'message' => $result,
            ]);
        } catch (PackageLimitExceededException $e) {
            // metricKey() 'active_package' (requireActivePackage()'s own
            // marker) means the company simply isn't a paying customer
            // right now — 403 Forbidden, matching the "you're not allowed
            // to do this at all" semantics. Any other metric key (right
            // now, only 'broadcast_send') means they ARE active but have
            // used up this period's quota — 429 Too Many Requests, since
            // that's a temporary, retry-later condition rather than a
            // permission problem. $e->getMessage() is already a safe,
            // human-readable Indonesian sentence (see that exception
            // class's own docblock) — no translation/mapping needed here,
            // unlike describeSendFailure() below which exists specifically
            // because the Go backend's raw errors AREN'T safe to surface
            // as-is.
            Log::warning('WaApiSendMessageController: blocked by package/quota guard', [
                'api_key_id' => $apiKey->id,
                'company_id' => $apiKey->company_id,
                'device_id' => $apiKey->device_id,
                'metric' => $e->metricKey(),
                'reason' => $e->getMessage(),
            ]);

            $isPackageInactive = $e->metricKey() === 'active_package';
            $httpStatus = $isPackageInactive ? 403 : 429;

            $usage->record(
                $apiKey,
                WaApiRequestLog::ENDPOINT_SEND_MESSAGE,
                $isPackageInactive ? WaApiRequestLog::STATUS_BLOCKED_PACKAGE : WaApiRequestLog::STATUS_BLOCKED_QUOTA,
                $httpStatus,
                $logAttributes + ['error' => mb_substr($e->getMessage(), 0, 500)]
            );

            return response()->json([
                'error' => $e->getMessage(),
            ], $httpStatus);
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

            $usage->record($apiKey, WaApiRequestLog::ENDPOINT_SEND_MESSAGE, WaApiRequestLog::STATUS_FAILED, 502, $logAttributes + [
                'error' => mb_substr($reason, 0, 500),
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
