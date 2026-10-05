<?php

namespace App\Http\Controllers\Chat\Widget;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\ChatWidgetConversation;
use App\Models\ChatWidgetMessage;
use App\Services\Chat\Widget\ChatWidgetService;
use App\Services\Company\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Live Chat Inbox: CS melihat percakapan website, mengambil alih dari AI,
 * membalas, dan menutup. Halaman memakai polling ringan (list & pesan)
 * -- tanpa WebSocket dulu.
 */
class ChatWidgetInboxController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(private readonly ChatWidgetService $chats)
    {
    }

    public function index(Request $request): View
    {
        return view('chat.widgets.inbox');
    }

    /** Daftar percakapan (JSON, di-poll halaman inbox). */
    public function conversations(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $items = $this->scoped($this->companyContext($request))
            ->with(['widget:id,name', 'assignee:id,name'])
            ->addSelect(['last_body' => ChatWidgetMessage::select('body')
                ->whereColumn('chat_widget_conversation_id', 'chat_widget_conversations.id')
                ->latest('id')
                ->limit(1)])
            ->when(in_array($status, array_keys(ChatWidgetConversation::STATUS_LABELS), true), fn (Builder $query) => $query->where('status', $status))
            ->when(! $status, fn (Builder $query) => $query->where('status', '!=', ChatWidgetConversation::STATUS_CLOSED))
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(fn (ChatWidgetConversation $conversation) => [
                'id' => $conversation->id,
                'name' => $conversation->displayName(),
                'phone' => $conversation->visitor_phone,
                'widget' => $conversation->widget?->name,
                'page_url' => $conversation->page_url,
                'status' => $conversation->status,
                'status_label' => ChatWidgetConversation::STATUS_LABELS[$conversation->status] ?? $conversation->status,
                'assignee' => $conversation->assignee?->name,
                'last' => $conversation->last_body,
                'at' => $conversation->last_message_at?->diffForHumans(),
            ]);

        return response()->json(['conversations' => $items]);
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $conversation = $this->scoped($this->companyContext($request))->findOrFail($id);

        return response()->json($this->state($conversation, (int) $request->query('after', 0)));
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $conversation = $this->scoped($this->companyContext($request))->findOrFail($id);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);

        $this->chats->agentReply($conversation, $request->user(), trim($validated['body']));

        return response()->json($this->state($conversation->fresh(), (int) $request->input('after', 0)));
    }

    public function takeOver(Request $request, string $id): JsonResponse
    {
        $conversation = $this->scoped($this->companyContext($request))->findOrFail($id);
        $this->chats->takeOver($conversation, $request->user());

        return response()->json($this->state($conversation->fresh(), (int) $request->input('after', 0)));
    }

    public function close(Request $request, string $id): JsonResponse
    {
        $conversation = $this->scoped($this->companyContext($request))->findOrFail($id);
        $this->chats->close($conversation);

        return response()->json($this->state($conversation->fresh(), (int) $request->input('after', 0)));
    }

    /** Percakapan milik widget company ini (staff: cabangnya saja). */
    private function scoped(CompanyContext $context): Builder
    {
        return ChatWidgetConversation::whereHas('widget', fn (Builder $query) => $query
            ->where('company_id', $context->company->id)
            ->when(! $context->isOwner, fn (Builder $q) => $q->where('branch_office_id', $context->branchOffice?->id)));
    }

    private function state(ChatWidgetConversation $conversation, int $afterId): array
    {
        return [
            'status' => $conversation->status,
            'status_label' => ChatWidgetConversation::STATUS_LABELS[$conversation->status] ?? $conversation->status,
            'messages' => ChatWidgetMessage::with('user:id,name')
                ->where('chat_widget_conversation_id', $conversation->id)
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit(200)
                ->get()
                ->map->toPayload()
                ->all(),
        ];
    }
}
