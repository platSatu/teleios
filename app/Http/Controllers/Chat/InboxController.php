<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaChatLabel;
use App\Models\WaChatLabelAssignment;
use App\Models\WaChatNote;
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
 *
 * labels()/attachLabel()/detachLabel() below tag a specific chat with
 * the company's App\Models\WaChatLabel catalog (managed separately at
 * Chat > Pengaturan > Label, see Chat\ChatLabelController) — kept here
 * rather than on that controller since these three are scoped to one
 * device+chat, the same shape as every other method in this class.
 */
class InboxController extends Controller
{
    use ResolvesCompanyContext;

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
     * AJAX: every label the company has defined, flagged with whether
     * each one is currently attached to this chat — powers the LABELS
     * section of the Inbox detail panel.
     */
    public function labels(Request $request, string $device, string $jid): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $all = WaChatLabel::where('company_id', $company->id)
            ->orderBy('name')
            ->get();

        $assignedIds = WaChatLabelAssignment::where('company_id', $company->id)
            ->where('device_id', $device)
            ->where('chat_jid', $jid)
            ->pluck('wa_chat_label_id');

        return response()->json([
            'labels' => $all->map(fn (WaChatLabel $label) => [
                'id' => $label->id,
                'name' => $label->name,
                'color' => $label->color,
                'assigned' => $assignedIds->contains($label->id),
            ]),
        ]);
    }

    /**
     * AJAX: tag this chat with one of the company's labels. Idempotent —
     * clicking an already-assigned label again is a no-op, not an error
     * or a duplicate row (see the assignments table's unique index).
     */
    public function attachLabel(Request $request, string $device, string $jid): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validated = $request->validate([
            'wa_chat_label_id' => ['required', 'uuid'],
        ]);

        $label = WaChatLabel::where('company_id', $company->id)
            ->where('id', $validated['wa_chat_label_id'])
            ->firstOrFail();

        WaChatLabelAssignment::firstOrCreate([
            'wa_chat_label_id' => $label->id,
            'company_id' => $company->id,
            'device_id' => $device,
            'chat_jid' => $jid,
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * AJAX: remove one label from this chat.
     */
    public function detachLabel(Request $request, string $device, string $jid, string $labelId): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        WaChatLabelAssignment::where('company_id', $company->id)
            ->where('device_id', $device)
            ->where('chat_jid', $jid)
            ->where('wa_chat_label_id', $labelId)
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * AJAX: every note on this chat, newest first — powers the NOTES
     * section of the Inbox detail panel.
     */
    public function notes(Request $request, string $device, string $jid): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $notes = WaChatNote::with('author')
            ->where('company_id', $company->id)
            ->where('device_id', $device)
            ->where('chat_jid', $jid)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'notes' => $notes->map(fn (WaChatNote $note) => [
                'id' => $note->id,
                'note' => $note->note,
                'author' => $note->author->name ?? null,
                'created_at' => $note->created_at?->toIso8601String(),
                'updated_at' => $note->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    /**
     * AJAX: add a note to this chat.
     */
    public function addNote(Request $request, string $device, string $jid): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $note = WaChatNote::create([
            'company_id' => $company->id,
            'device_id' => $device,
            'chat_jid' => $jid,
            'created_by' => $request->user()?->id,
            'note' => $validated['note'],
        ]);

        return response()->json(['status' => 'ok', 'id' => $note->id]);
    }

    /**
     * AJAX: edit a note's text. The note id travels in the request body
     * (not the URL) so this stays on the same 2-placeholder route shape
     * (device, jid) as every other Inbox route — see labelDetachUrl in
     * inbox.blade.php for why a 3rd URL placeholder is avoided here.
     */
    public function updateNote(Request $request, string $device, string $jid): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validated = $request->validate([
            'id' => ['required', 'uuid'],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $note = WaChatNote::where('company_id', $company->id)
            ->where('device_id', $device)
            ->where('chat_jid', $jid)
            ->where('id', $validated['id'])
            ->firstOrFail();

        $note->update(['note' => $validated['note']]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * AJAX: delete a note. Same "id in the body, not the URL" shape as
     * updateNote() above.
     */
    public function deleteNote(Request $request, string $device, string $jid): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validated = $request->validate([
            'id' => ['required', 'uuid'],
        ]);

        WaChatNote::where('company_id', $company->id)
            ->where('device_id', $device)
            ->where('chat_jid', $jid)
            ->where('id', $validated['id'])
            ->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * AJAX: this chat's media messages (image/video/document), for the
     * detail panel's MEDIA & FILES tabs. ?type= filters to one of those
     * three — see InboxService::mediaList().
     */
    public function mediaList(Request $request, string $device, string $jid): JsonResponse
    {
        $type = $request->query('type', 'image');

        return $this->safeJson(fn (string $jwt) => [
            'items' => $this->inboxService->mediaList($jwt, $device, $jid, $type),
        ]);
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
