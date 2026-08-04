<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Services\Chat\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

/**
 * The chat UI itself (list of conversations + message thread) for one of
 * the logged-in user's connected devices. Pairing/device management
 * lives in ConnectDeviceController, kept separate on purpose. Every
 * route here carries a {device} id, since a user can have several
 * devices connected and each has its own independent set of chats.
 */
class InboxController extends Controller
{
    public function __construct(
        protected InboxService $inboxService,
    ) {}

    public function index(string $device): View|RedirectResponse
    {
        if (! session('golang_jwt_token')) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['golang' => 'Sesi WhatsApp tidak ditemukan, silakan login ulang.']);
        }

        return view('chat.inbox.inbox', ['deviceId' => $device]);
    }

    /**
     * AJAX: list the conversations on one device.
     */
    public function chats(string $device): JsonResponse
    {
        return $this->safeJson(fn (string $jwt) => [
            'chats' => $this->inboxService->chats($jwt, $device),
        ]);
    }

    /**
     * AJAX: message history for one chat on one device. ?after_id=<id>
     * switches this from "recent history" to "what's new since <id>" —
     * see InboxService::messages().
     */
    public function messages(Request $request, string $device, string $jid): JsonResponse
    {
        $afterId = (int) $request->query('after_id', 0);

        return $this->safeJson(fn (string $jwt) => [
            'messages' => $this->inboxService->messages($jwt, $device, $jid, $afterId),
        ]);
    }

    /**
     * AJAX: send a text message to one chat through one device.
     */
    public function send(Request $request, string $device, string $jid): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string', 'max:4096'],
        ]);

        return $this->safeJson(fn (string $jwt) => [
            'message' => $this->inboxService->send($jwt, $device, $jid, $request->string('body')->value()),
        ]);
    }

    /**
     * AJAX: online/typing state for one chat contact.
     */
    public function presence(string $device, string $jid): JsonResponse
    {
        return $this->safeJson(fn (string $jwt) => $this->inboxService->presence($jwt, $device, $jid));
    }

    /**
     * AJAX: upload + send a media message (image/video/audio/document/
     * sticker) to one chat through one device. 32MB matches the cap
     * WaMediaController enforces on the Go side — rejecting an oversized
     * file here means the browser never even uploads it, instead of
     * uploading the whole thing only for Go to reject it.
     */
    public function sendMedia(Request $request, string $device, string $jid): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:32768'],
            'caption' => ['nullable', 'string', 'max:1024'],
            'as_sticker' => ['nullable', 'boolean'],
        ]);

        return $this->safeJson(fn (string $jwt) => [
            'message' => $this->inboxService->sendMedia(
                $jwt,
                $device,
                $jid,
                $request->file('file'),
                $request->input('caption'),
                $request->boolean('as_sticker'),
            ),
        ]);
    }

    /**
     * Streams one message's stored media file back to the browser (used
     * as the src= of <img>/<video>/<audio> tags and document download
     * links in the chat thread). Not JSON — this can't go through
     * safeJson() like everything else here, since the response body is
     * the raw file, not a JSON envelope.
     */
    public function media(string $device, int $messageId): Response
    {
        $jwt = session('golang_jwt_token');

        if (! $jwt) {
            abort(401, 'Sesi WhatsApp tidak ditemukan.');
        }

        try {
            $result = $this->inboxService->media($jwt, $device, $messageId);
        } catch (Throwable $e) {
            report($e);
            abort(404, 'Media tidak ditemukan.');
        }

        return response($result['body'], 200, array_filter([
            'Content-Type' => $result['content_type'],
            'Content-Disposition' => $result['content_disposition'],
        ]));
    }

    /**
     * Run a callback that needs the Golang JWT, translating a missing
     * session or an upstream failure into a consistent JSON error
     * response instead of leaking exceptions to the frontend.
     */
    protected function safeJson(callable $callback): JsonResponse
    {
        $jwt = session('golang_jwt_token');

        if (! $jwt) {
            return response()->json(['error' => 'Sesi WhatsApp tidak ditemukan.'], 401);
        }

        try {
            return response()->json($callback($jwt));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'Gagal menghubungi server WhatsApp.'], 502);
        }
    }
}
