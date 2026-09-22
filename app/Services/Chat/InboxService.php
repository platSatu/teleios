<?php

namespace App\Services\Chat;

use App\Models\Company;
use App\Services\PackageLimitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Talks to the Go backend's chat endpoints on behalf of the logged-in
 * user: listing conversations, reading message history, and sending
 * outgoing WhatsApp messages. Every call is scoped to one of the user's
 * devices, since a user can have several connected at once.
 */
class InboxService
{
    protected string $baseUrl;

    protected string $apiKey;

    public function __construct(protected PackageLimitService $packageLimits)
    {
        $this->baseUrl = rtrim(config('services.golang.url'), '/');
        $this->apiKey = config('services.golang.key');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function chats(string $jwt, string $deviceId): array
    {
        return $this->request('get', "/api/wa/devices/{$deviceId}/chats", $jwt)['chats'] ?? [];
    }

    /**
     * @param  int  $afterSeq  When 0 (default), returns the most recent
     *                         page of history (used when a chat is first
     *                         opened). When > 0, returns only messages
     *                         with a higher Seq than that — a small delta
     *                         the caller can append, used by polling so it
     *                         doesn't have to re-fetch the whole thread
     *                         every few seconds. Seq (a plain
     *                         auto-increment counter), not id (a random
     *                         UUID with no natural order) — see
     *                         g_backend's models.WaMessage.
     * @return array<int, array<string, mixed>>
     */
    public function messages(string $jwt, string $deviceId, string $chatJid, int $afterSeq = 0): array
    {
        $path = "/api/wa/devices/{$deviceId}/chats/".rawurlencode($chatJid).'/messages';
        if ($afterSeq > 0) {
            $path .= '?after_seq='.$afterSeq;
        }

        return $this->request('get', $path, $jwt)['messages'] ?? [];
    }

    /**
     * @param  ?Company  $company  Pass this to have the send guarded by
     *                             the company's package/quota (see
     *                             guardPackageLimit()'s docblock) — omit
     *                             (the default) to keep this call's
     *                             existing, unguarded behavior exactly
     *                             as it was before checklist item #3
     *                             (CLAUDE.md) started being rolled out
     *                             caller-by-caller.
     * @return array<string, mixed>
     */
    public function send(string $jwt, string $deviceId, string $chatJid, string $body, ?Company $company = null, ?string $limitMetric = 'broadcast_send'): array
    {
        $this->guardPackageLimit($company, $limitMetric);

        try {
            return $this->request('post', "/api/wa/devices/{$deviceId}/chats/".rawurlencode($chatJid).'/messages', $jwt, [
                'body' => $body,
            ])['message'] ?? [];
        } catch (Throwable $e) {
            $this->releasePackageLimit($company, $limitMetric);

            throw $e;
        }
    }

    /**
     * Sends a native WhatsApp poll (survey) to a chat — the anti-ban-safe
     * "interactive message" this app supports (see WaMessageTypePoll's
     * docblock on the Go side for why buttons/list messages are
     * deliberately not offered: WhatsApp actively blocks/deprioritizes
     * those from unofficial connections like this one's, but a poll is
     * an ordinary consumer-app feature with no such restriction).
     *
     * @param  array<int, string>  $options  At least 2 option strings.
     * @param  int  $selectableCount  How many options a voter may pick at
     *                                once; 1 for an ordinary single-choice
     *                                survey (the common case).
     * @param  ?Company  $company  See send()'s docblock — same opt-in guard.
     * @return array<string, mixed>
     */
    public function sendPoll(string $jwt, string $deviceId, string $chatJid, string $question, array $options, int $selectableCount = 1, ?Company $company = null, ?string $limitMetric = 'broadcast_send'): array
    {
        $this->guardPackageLimit($company, $limitMetric);

        try {
            return $this->request('post', "/api/wa/devices/{$deviceId}/chats/".rawurlencode($chatJid).'/polls', $jwt, [
                'question' => $question,
                'options' => $options,
                'selectable_count' => $selectableCount,
            ])['message'] ?? [];
        } catch (Throwable $e) {
            $this->releasePackageLimit($company, $limitMetric);

            throw $e;
        }
    }

    /**
     * Fetches a poll's current tally: the question/options it was sent
     * with, plus every voter's current selection. $chatJid is only used
     * to build the URL (Go's route nests polls under a chat for
     * consistency with its other endpoints) — the poll itself is looked
     * up by $pollMessageId within $deviceId regardless.
     *
     * @return array{poll: array<string, mixed>, votes: array<int, array<string, mixed>>}
     */
    public function pollResults(string $jwt, string $deviceId, string $chatJid, string $pollMessageId): array
    {
        $path = "/api/wa/devices/{$deviceId}/chats/".rawurlencode($chatJid)."/polls/{$pollMessageId}/results";

        return $this->request('get', $path, $jwt);
    }

    /**
     * Forwards an uploaded file to the Go backend as a multipart request,
     * which uploads it to WhatsApp's media servers and sends it as an
     * image/video/audio/document/sticker message — see
     * WaMediaController::SendMedia on the Go side. Kept separate from
     * request() (used by every other method here) since that helper only
     * knows how to send a plain JSON body, not multipart form data.
     *
     * @param  ?Company  $company  See send()'s docblock — same opt-in guard.
     * @return array<string, mixed>
     */
    public function sendMedia(string $jwt, string $deviceId, string $chatJid, UploadedFile $file, ?string $caption, bool $asSticker, ?Company $company = null, ?string $limitMetric = 'broadcast_send'): array
    {
        $this->guardPackageLimit($company, $limitMetric);

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Authorization' => 'Bearer '.trim($jwt),
                'Accept' => 'application/json',
            ])
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName(), [
                    'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
                ])
                ->post(
                    "{$this->baseUrl}/api/wa/devices/{$deviceId}/chats/".rawurlencode($chatJid).'/media',
                    array_filter([
                        'caption' => $caption,
                        'as_sticker' => $asSticker ? '1' : null,
                    ], fn ($value) => $value !== null)
                );

            if ($response->failed()) {
                throw new RuntimeException("Golang media send to {$chatJid} failed: ".$response->body());
            }

            return $response->json()['message'] ?? [];
        } catch (Throwable $e) {
            $this->releasePackageLimit($company, $limitMetric);

            throw $e;
        }
    }

    /**
     * Same as sendMedia(), but for a file that's already sitting on local
     * disk (a WaMessageTemplate's attachment_path) rather than a fresh
     * browser UploadedFile — used by send paths that have no live HTTP
     * upload to read from: GoogleFormWebhookController and
     * App\Jobs\SendScheduledWaMessage. Go's media endpoint doesn't care
     * where the multipart bytes originally came from, so this reuses the
     * exact same request shape as sendMedia().
     *
     * @param  ?Company  $company  See send()'s docblock — same opt-in guard.
     * @return array<string, mixed>
     */
    public function sendStoredMedia(string $jwt, string $deviceId, string $chatJid, string $absolutePath, string $filename, ?string $mimeType, ?string $caption, ?Company $company = null, ?string $limitMetric = 'broadcast_send'): array
    {
        $this->guardPackageLimit($company, $limitMetric);

        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Authorization' => 'Bearer '.trim($jwt),
                'Accept' => 'application/json',
            ])
                ->attach('file', file_get_contents($absolutePath), $filename, [
                    'Content-Type' => $mimeType ?: 'application/octet-stream',
                ])
                ->post(
                    "{$this->baseUrl}/api/wa/devices/{$deviceId}/chats/".rawurlencode($chatJid).'/media',
                    array_filter(['caption' => $caption], fn ($value) => $value !== null)
                );

            if ($response->failed()) {
                throw new RuntimeException("Golang media send to {$chatJid} failed: ".$response->body());
            }

            return $response->json()['message'] ?? [];
        } catch (Throwable $e) {
            $this->releasePackageLimit($company, $limitMetric);

            throw $e;
        }
    }

    /**
     * Fetches one message's stored media bytes from the Go backend, to be
     * streamed back to the browser by InboxController::media(). Buffered
     * in memory rather than proxied byte-by-byte — acceptable since
     * WaMediaController caps uploads at 32MB on the way in, so this is
     * never large enough for buffering to be a real problem.
     *
     * @return array{body: string, content_type: string, content_disposition: string}
     */
    public function media(string $jwt, string $deviceId, string $messageId): array
    {
        $response = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'Authorization' => 'Bearer '.trim($jwt),
        ])->get("{$this->baseUrl}/api/wa/devices/{$deviceId}/media/{$messageId}");

        if ($response->failed()) {
            throw new RuntimeException("Golang media fetch for message {$messageId} failed: ".$response->body());
        }

        return [
            'body' => $response->body(),
            'content_type' => $response->header('Content-Type') ?: 'application/octet-stream',
            'content_disposition' => $response->header('Content-Disposition') ?: '',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mediaList(string $jwt, string $deviceId, string $chatJid, string $type): array
    {
        $path = "/api/wa/devices/{$deviceId}/chats/".rawurlencode($chatJid).'/media-list?type='.rawurlencode($type);

        return $this->request('get', $path, $jwt)['items'] ?? [];
    }

    /**
     * @return array{state: string, last_seen: string}
     */
    public function presence(string $jwt, string $deviceId, string $chatJid): array
    {
        return $this->request('get', "/api/wa/devices/{$deviceId}/chats/".rawurlencode($chatJid).'/presence', $jwt);
    }

    /**
     * @throws RuntimeException if the Go backend rejects the request or is
     *                          unreachable.
     */
    /**
     * Turns a RuntimeException thrown by send()/request() into a message
     * a company can actually act on, instead of the raw "Golang inbox
     * request to /api/wa/devices/.../chats/...%40s... failed: {"error":
     * "..."}" string that used to leak straight into user-facing UI
     * (the Google Form integration's submission log, and Pesan
     * Terjadwal's History Pengiriman "Keterangan" column) — both call
     * this now instead of each hand-rolling their own copy.
     *
     * The Go backend's own JSON error body is embedded verbatim at the
     * end of that message (see request()'s throw above) — this pulls the
     * `error` key back out when present and maps the couple of causes a
     * company can actually self-serve on (device missing / device
     * disconnected) to a clear Indonesian sentence.
     */
    public static function describeSendFailure(\Throwable $e): string
    {
        $reason = self::extractGoErrorReason($e);

        if ($reason === null) {
            return 'Gagal mengirim pesan. Pastikan device masih terhubung.';
        }

        if (str_contains($reason, 'device not found')) {
            return 'Device pengirim untuk jadwal ini tidak ditemukan (mungkin sudah dihapus atau dipindah ke company lain). Buka Edit Jadwal dan pilih ulang device pengirimnya.';
        }

        if (str_contains($reason, 'not connected')) {
            return 'Device pengirim sedang tidak terhubung ke WhatsApp. Sambungkan ulang di menu Connect Device, lalu tunggu jadwal berikutnya atau kirim ulang.';
        }

        return "Gagal mengirim pesan: {$reason}.";
    }

    /**
     * True when a send failed specifically because the device has no
     * live WhatsApp session right now (g_backend's "wa: device is not
     * connected" / "wa: timed out waiting to send" — see
     * WaConnectDeviceService.EnsureConnectedClient and AcquireSendSlot
     * on the Go side) — distinct from every other failure reason
     * (device deleted, an invalid recipient, a genuine WhatsApp API
     * rejection, ...).
     *
     * Callers like App\Jobs\SendScheduledWaMessage treat this case very
     * differently from a real send failure: a device being offline is
     * often temporary (a QR re-scan, a flaky connection, a brief
     * process restart) and worth quietly retrying for a while, instead
     * of burning through the job's normal $tries/backoff and
     * permanently failing minutes before the device likely reconnects —
     * see SendScheduledWaMessage::MAX_DEVICE_OFFLINE_REDISPATCHES's
     * docblock for the full reasoning.
     */
    public static function isDeviceDisconnected(\Throwable $e): bool
    {
        $reason = self::extractGoErrorReason($e);

        return $reason !== null && str_contains($reason, 'not connected');
    }

    /**
     * Pulls the Go backend's own `{"error": "..."}` JSON body back out of
     * a RuntimeException's message (see request()'s throw below, which
     * embeds it verbatim) — shared by describeSendFailure() and
     * isDeviceDisconnected() so both read the exact same reason string
     * rather than parsing it twice.
     */
    private static function extractGoErrorReason(\Throwable $e): ?string
    {
        if (! preg_match('/\{.*\}\s*$/s', $e->getMessage(), $matches)) {
            return null;
        }

        $decoded = json_decode($matches[0], true);

        if (! is_array($decoded) || empty($decoded['error'])) {
            return null;
        }

        return (string) $decoded['error'];
    }

    protected function request(string $method, string $path, string $jwt, array $payload = []): array
    {
        $response = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'Authorization' => 'Bearer '.trim($jwt),
            'Accept' => 'application/json',
        ])->{$method}("{$this->baseUrl}{$path}", $payload);

        if ($response->failed()) {
            throw new RuntimeException("Golang inbox request to {$path} failed: ".$response->body());
        }

        return $response->json();
    }

    /**
     * Centralized package/quota guard for every send path in this
     * service (CLAUDE.md checklist item #3, Fase 1). $company is
     * optional and defaults to null so this stays fully backward
     * compatible: a caller that doesn't pass it gets exactly the old,
     * unguarded behavior — this lets the centralization be rolled out
     * caller-by-caller (only the callers that have actually been
     * reviewed/tested pass $company) instead of silently changing
     * behavior for every one of send()/sendPoll()/sendMedia()/
     * sendStoredMedia()'s callers app-wide in one shot. See CLAUDE.md's
     * 22 September 2026 note on checklist #3 for which callers are
     * opted in so far and which are still pending review.
     *
     * Deliberately does NOT touch App\Services\Chat\
     * BroadcastThrottleService here, unlike PackageLimitService::
     * reserve()/release() — a throttle attempt() call PERMANENTLY
     * CONSUMES a rate-limit slot the moment it's called (see that
     * service's docblock), it isn't a safe, idempotent check like
     * requireActivePackage(). The jobs opting into this guard so far
     * (SendScheduledWaMessage, SendAutoReplyMessage, SendAiBotReply)
     * already call throttle->attempt() themselves before ever reaching
     * this method, with their own "not a failure, redispatch with a
     * delay" handling wrapped around it. If this guard called attempt()
     * too, every one of their sends would burn TWO rate-limit slots
     * instead of one — quietly halving each device's real throughput
     * against the ceiling it's supposed to respect. Centralizing
     * throttle safely needs its own follow-up (a typed exception each
     * caller's existing redispatch logic can catch instead), tracked
     * separately rather than folded into this pass.
     *
     * Reuses App\Exceptions\PackageLimitExceededException (thrown by
     * requireActivePackage()/reserve() themselves) rather than a new
     * exception type — every caller opted in so far already has a catch
     * block for this exact exception (for their own now-removed manual
     * calls to the same two methods), so opting a caller in only means
     * passing $company through, never touching its catch logic.
     *
     * $limitMetric may be null — for a caller whose messages aren't
     * metered against any quota (e.g. auto-reply/AI bot right now: see
     * CLAUDE.md checklist item #8, still an open decision on whether
     * they ever should be) — in which case only requireActivePackage()
     * runs and reserve() is skipped entirely, never touching any
     * CompanyLimitUsage row. Passing 'broadcast_send' here for a caller
     * that isn't supposed to be metered would silently start consuming
     * a real company's broadcast quota for messages that were never
     * counted against it before, which is exactly the regression this
     * split is here to prevent.
     */
    private function guardPackageLimit(?Company $company, ?string $limitMetric): void
    {
        if ($company === null) {
            return;
        }

        $this->packageLimits->requireActivePackage($company);

        if ($limitMetric !== null) {
            $this->packageLimits->reserve($company, $limitMetric);
        }
    }

    /**
     * Mirrors guardPackageLimit() — gives back a reservation it made,
     * when the send that followed then failed, so a retried/failed
     * attempt never permanently burns quota it never actually used.
     * No-op when $company is null, for the same opt-in reason as
     * guardPackageLimit(). Safe to call even when guardPackageLimit()
     * never actually reserved anything (e.g. it threw before reaching
     * reserve(), or the company has no active package/limit configured
     * at all) — PackageLimitService::release() itself already no-ops in
     * every one of those cases and floors at 0 rather than going
     * negative.
     */
    private function releasePackageLimit(?Company $company, ?string $limitMetric): void
    {
        if ($company === null || $limitMetric === null) {
            return;
        }

        $this->packageLimits->release($company, $limitMetric);
    }
}
