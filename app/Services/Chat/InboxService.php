<?php

namespace App\Services\Chat;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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

    public function __construct()
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
     * @param  int  $afterId  When 0 (default), returns the most recent
     *                        page of history (used when a chat is first
     *                        opened). When > 0, returns only messages
     *                        newer than that message ID — a small delta
     *                        the caller can append, used by polling so it
     *                        doesn't have to re-fetch the whole thread
     *                        every few seconds.
     * @return array<int, array<string, mixed>>
     */
    public function messages(string $jwt, string $deviceId, string $chatJid, int $afterId = 0): array
    {
        $path = "/api/wa/devices/{$deviceId}/chats/".rawurlencode($chatJid).'/messages';
        if ($afterId > 0) {
            $path .= '?after_id='.$afterId;
        }

        return $this->request('get', $path, $jwt)['messages'] ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function send(string $jwt, string $deviceId, string $chatJid, string $body): array
    {
        return $this->request('post', "/api/wa/devices/{$deviceId}/chats/".rawurlencode($chatJid).'/messages', $jwt, [
            'body' => $body,
        ])['message'] ?? [];
    }

    /**
     * Forwards an uploaded file to the Go backend as a multipart request,
     * which uploads it to WhatsApp's media servers and sends it as an
     * image/video/audio/document/sticker message — see
     * WaMediaController::SendMedia on the Go side. Kept separate from
     * request() (used by every other method here) since that helper only
     * knows how to send a plain JSON body, not multipart form data.
     *
     * @return array<string, mixed>
     */
    public function sendMedia(string $jwt, string $deviceId, string $chatJid, UploadedFile $file, ?string $caption, bool $asSticker): array
    {
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
     * @return array<string, mixed>
     */
    public function sendStoredMedia(string $jwt, string $deviceId, string $chatJid, string $absolutePath, string $filename, ?string $mimeType, ?string $caption): array
    {
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
    public function media(string $jwt, string $deviceId, int $messageId): array
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
            return 'Device pengirim untuk jadwal ini tidak ditemukan (mungkin sudah dihapus atau dipindah ke company lain). Buka Edit Jadwal dan pilih ulang device pengirimnya.';
        }

        if (str_contains($reason, 'not connected')) {
            return 'Device pengirim sedang tidak terhubung ke WhatsApp. Sambungkan ulang di menu Connect Device, lalu tunggu jadwal berikutnya atau kirim ulang.';
        }

        return "Gagal mengirim pesan: {$reason}.";
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
}
