<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaConversation;
use App\Services\Chat\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Chat ops" endpoints layered on top of one WhatsApp thread: current
 * status (open/pending/resolved), SLA state, and who's assigned to work
 * it — backed by App\Models\WaConversation, all writes going through
 * App\Services\Chat\ConversationService so the status machine/SLA math
 * lives in exactly one place. Kept as its own controller (rather than
 * folded into Chat\InboxController, which already owns labels/notes/
 * contact on the same device+jid shape) since this is a big enough
 * concern on its own and is the one piece of the Inbox detail panel
 * that's about internal team workflow rather than the chat's content.
 */
class ConversationController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(
        protected ConversationService $conversations,
    ) {}

    /**
     * The company-wide "Percakapan" queue page shell — see queue() for
     * the JSON data it polls, same page-shell/JSON-data split every other
     * AJAX-driven screen in this app uses (e.g. Chat\ConnectDeviceController
     * ::index() vs ::list()).
     */
    public function index(Request $request): View
    {
        $context = $this->companyContext($request);

        $teamMembers = $this->companyTeamMembers(
            $context->company,
            $context->isLockedToBranch() ? $context->branchOffice?->id : null
        );

        return view('chat.conversations.index', [
            'teamMembers' => $teamMembers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all(),
        ]);
    }

    /**
     * AJAX: this thread's current chat-ops state — powers the detail
     * panel's status pill, SLA badge, and assignee dropdown. Read-only;
     * never creates a conversation row (that only ever happens from an
     * actual inbound/outbound message, see ConversationService) — a chat
     * with no message history yet simply has no conversation object.
     */
    public function show(Request $request, string $device, string $jid): JsonResponse
    {
        $context = $this->companyContext($request);

        $conversation = WaConversation::where('company_id', $context->company->id)
            ->where('device_id', $device)
            ->where('chat_jid', $jid)
            ->with('assignee:id,name')
            ->first();

        $teamMembers = $this->companyTeamMembers(
            $context->company,
            $context->isLockedToBranch() ? $context->branchOffice?->id : null
        );

        return response()->json([
            'conversation' => $conversation ? $this->present($conversation) : null,
            'team_members' => $teamMembers->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all(),
        ]);
    }

    /**
     * AJAX: manual status change ("Tandai Selesai" / "Buka Kembali" /
     * "Tunda"). 404s rather than silently no-op-ing when the
     * conversation doesn't exist yet — a status change only makes sense
     * on a thread that has actually exchanged at least one message.
     */
    public function updateStatus(Request $request, string $device, string $jid): JsonResponse
    {
        $context = $this->companyContext($request);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', WaConversation::STATUSES)],
        ]);

        $conversation = WaConversation::where('company_id', $context->company->id)
            ->where('device_id', $device)
            ->where('chat_jid', $jid)
            ->firstOrFail();

        $conversation = $this->conversations->setStatus($conversation, $validated['status']);

        return response()->json(['conversation' => $this->present($conversation)]);
    }

    /**
     * AJAX: manually (re)assign this thread to a team member, or clear
     * it (assigned_to sent empty/null) — overrides whatever
     * ConversationService::autoAssign() picked. Separate from
     * Chat\InboxController::assignContact(): that one hands the
     * *customer* (wa_contacts, account ownership) to someone; this one
     * hands the *conversation thread* (this one incident/session) to
     * someone, which is what actually drives the SLA queue.
     */
    public function assign(Request $request, string $device, string $jid): JsonResponse
    {
        $context = $this->companyContext($request);

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $conversation = WaConversation::where('company_id', $context->company->id)
            ->where('device_id', $device)
            ->where('chat_jid', $jid)
            ->firstOrFail();

        $conversation = $this->conversations->reassign($conversation, $validated['assigned_to'] ?? null);
        $conversation->load('assignee:id,name');

        return response()->json(['conversation' => $this->present($conversation)]);
    }

    /**
     * AJAX: the company's whole conversation queue, across every device
     * — the "ops dashboard" view (open/pending SLA state, who's
     * assigned) rather than any single device's chat list. Filterable by
     * ?status= and ?assigned_to=me so the Inbox sidebar can offer "My
     * Open Chats" / "Breached SLA" style views without pulling every row
     * to the browser to filter client-side.
     */
    public function queue(Request $request): JsonResponse
    {
        $context = $this->companyContext($request);

        $query = WaConversation::forCompany($context->company->id)
            ->with(['assignee:id,name', 'contact:id,name,phone'])
            ->orderByDesc('last_inbound_at');

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        if ($request->filled('status')) {
            $query->whereIn('status', explode(',', (string) $request->query('status')));
        }

        if ($request->query('assigned_to') === 'me') {
            $query->where('assigned_to', $request->user()->id);
        } elseif ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->query('assigned_to'));
        }

        if ($request->boolean('breached_only')) {
            $query->where(function ($q) {
                $q->where('first_response_breached', true)->orWhere('resolution_breached', true);
            });
        }

        $conversations = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'conversations' => collect($conversations->items())->map(fn (WaConversation $c) => $this->present($c))->all(),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(WaConversation $conversation): array
    {
        return [
            'device_id' => $conversation->device_id,
            'chat_jid' => $conversation->chat_jid,
            'status' => $conversation->status,
            'assigned_to' => $conversation->assigned_to,
            'assigned_to_name' => $conversation->assignee?->name,
            'contact_name' => $conversation->relationLoaded('contact') ? $conversation->contact?->name : null,
            'contact_phone' => $conversation->relationLoaded('contact') ? $conversation->contact?->phone : null,
            'opened_at' => $conversation->opened_at?->toIso8601String(),
            'first_response_at' => $conversation->first_response_at?->toIso8601String(),
            'resolved_at' => $conversation->resolved_at?->toIso8601String(),
            'last_inbound_at' => $conversation->last_inbound_at?->toIso8601String(),
            'last_outbound_at' => $conversation->last_outbound_at?->toIso8601String(),
            'sla_first_response_due_at' => $conversation->sla_first_response_due_at?->toIso8601String(),
            'sla_resolution_due_at' => $conversation->sla_resolution_due_at?->toIso8601String(),
            'first_response_breached' => $conversation->first_response_breached,
            'resolution_breached' => $conversation->resolution_breached,
        ];
    }
}
