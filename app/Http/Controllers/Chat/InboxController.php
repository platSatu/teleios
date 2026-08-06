<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaChatLabel;
use App\Models\WaChatLabelAssignment;
use App\Models\WaChatNote;
use App\Models\WaContact;
use App\Models\WaMessageQuickReply;
use App\Models\WaMessageTemplate;
use App\Services\Chat\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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
     * AJAX: this chat's CRM contact record — auto-creating/refreshing it
     * the first time a chat is opened (?phone= carries the Go-resolved
     * phone number the frontend already has from the chat list, since
     * that's the one place @lid resolution already happened; see
     * App\Models\WaContact's docblock for why phone rather than chat_jid
     * is the identity key). Powers the detail panel's assignee dropdown
     * and branch label — replaces what used to be a permanently-disabled
     * "+ Assign" button with no data behind it at all.
     *
     * Groups/channels have no individual phone number and so never get a
     * contact record — returns null for those rather than erroring, so
     * the detail panel can just hide the section.
     */
    public function contact(Request $request, string $device, string $jid): JsonResponse
    {
        $context = $this->companyContext($request);

        if (str_ends_with($jid, '@g.us') || str_ends_with($jid, '@newsletter')) {
            return response()->json(['contact' => null, 'team_members' => []]);
        }

        $phone = WaContact::normalizePhone((string) $request->query('phone', ''));

        $teamMembers = $this->companyTeamMembers(
            $context->company,
            $context->isLockedToBranch() ? $context->branchOffice?->id : null
        );

        if ($phone === '') {
            // Nothing to key a contact on yet (phone hasn't resolved on
            // the Go side, e.g. right after a brand new chat starts) —
            // still return the team member list so the UI has something
            // to render, just no contact row.
            return response()->json(['contact' => null, 'team_members' => $this->presentUsers($teamMembers)]);
        }

        $contact = WaContact::firstOrNew([
            'company_id' => $context->company->id,
            'phone' => $phone,
        ]);

        if (! $contact->exists) {
            $contact->branch_office_id = $context->isLockedToBranch() ? $context->branchOffice?->id : null;
            $contact->name = $request->string('name')->value() ?: null;
            $contact->source = 'whatsapp';
        } elseif (! $contact->name && $request->filled('name')) {
            $contact->name = $request->string('name')->value();
        }

        $contact->last_contacted_at = now();
        $contact->save();
        $contact->load(['branchOffice:id,name', 'assignee:id,name']);

        return response()->json([
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'branch_office_name' => $contact->branchOffice?->name,
                'assigned_to' => $contact->assigned_to,
                'assigned_to_name' => $contact->assignee?->name,
            ],
            'team_members' => $this->presentUsers($teamMembers),
        ]);
    }

    /**
     * AJAX: hand this chat's contact to a team member (or clear it —
     * assigned_to may be sent empty/null).
     */
    public function assignContact(Request $request, string $device, string $jid): JsonResponse
    {
        $context = $this->companyContext($request);

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $phone = WaContact::normalizePhone((string) $request->input('phone', ''));

        if ($phone === '') {
            return response()->json(['error' => 'Kontak belum ditemukan untuk chat ini.'], 422);
        }

        $contact = WaContact::where('company_id', $context->company->id)
            ->where('phone', $phone)
            ->firstOrFail();

        $contact->update(['assigned_to' => $validated['assigned_to'] ?? null]);
        $contact->load('assignee:id,name');

        return response()->json([
            'assigned_to' => $contact->assigned_to,
            'assigned_to_name' => $contact->assignee?->name,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\User>  $users
     * @return array<int, array<string, string>>
     */
    private function presentUsers($users): array
    {
        return $users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all();
    }

    /**
     * AJAX: assignee name for every contact the company has an
     * assignment on, keyed by phone — powers the small "assigned to"
     * badge on chat list rows. Deliberately one query for the whole
     * list (not one lookup per chat_jid) since the list can run to a
     * couple hundred rows and this is polled on the same cadence as
     * chats() — see the perf notes on renderChatList() in inbox.blade.php
     * for why per-row network calls are exactly what this avoids.
     */
    public function assignments(Request $request, string $device): JsonResponse
    {
        $context = $this->companyContext($request);

        $contacts = WaContact::where('company_id', $context->company->id)
            ->whereNotNull('assigned_to')
            ->with('assignee:id,name')
            ->get(['id', 'phone', 'assigned_to']);

        return response()->json([
            'assignments' => $contacts->mapWithKeys(fn (WaContact $c) => [
                $c->phone => $c->assignee?->name,
            ])->filter()->all(),
        ]);
    }

    /**
     * AJAX: this device's active "Balasan Cepat" (quick replies), for
     * the picker behind the lightning-bolt button / "/" shortcut in the
     * inbox's message box — see App\Models\WaMessageQuickReply and
     * Chat\MessageQuickReplyController (where these are actually
     * managed; this endpoint only ever reads).
     */
    public function quickReplies(Request $request, string $device): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $quickReplies = WaMessageQuickReply::where('company_id', $company->id)
            ->where('device_id', $device)
            ->where('status', 'active')
            ->orderBy('title')
            ->get(['id', 'title', 'shortcut', 'category', 'message_content']);

        return response()->json(['quick_replies' => $quickReplies]);
    }

    /**
     * AJAX: this company's active WA Templates, for the picker behind
     * the template button in the inbox's message box — see
     * App\Models\WaMessageTemplate and Chat\MessageTemplateController
     * (where these are actually managed; this endpoint only ever reads).
     * Company-wide, not per-device (unlike quickReplies() above) —
     * matches how wa_message_templates itself has no device_id column.
     */
    public function templates(Request $request, string $device): JsonResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        // ->usable() (not a bare status='active' filter) so a template
        // whose category has been disabled/archived can't still be picked
        // and sent from the inbox — see WaMessageTemplate::scopeUsable().
        $templates = WaMessageTemplate::where('company_id', $company->id)
            ->usable()
            ->orderBy('name')
            ->get([
                'id', 'name', 'header', 'template', 'footer', 'link', 'buttons',
                'content_type', 'attachment_path', 'attachment_type', 'attachment_original_name',
            ])
            ->map(function (WaMessageTemplate $template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    // Raw body text, kept for backward compatibility with
                    // any caller still reading `template` directly.
                    'template' => $template->template,
                    // Full header+body+link+buttons(as text)+footer text —
                    // this is what should actually be sent/inserted, so the
                    // picker no longer pastes the bare body only.
                    'composed' => $template->composedMessage(),
                    'content_type' => $template->content_type,
                    'attachment_url' => $template->attachment_path
                        ? Storage::disk('public')->url($template->attachment_path)
                        : null,
                    'attachment_type' => $template->attachment_type,
                    'attachment_original_name' => $template->attachment_original_name,
                ];
            });

        return response()->json(['templates' => $templates]);
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
