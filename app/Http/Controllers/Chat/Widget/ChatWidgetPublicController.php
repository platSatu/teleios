<?php

namespace App\Http\Controllers\Chat\Widget;

use App\Http\Controllers\Controller;
use App\Models\ChatWidget;
use App\Models\ChatWidgetConversation;
use App\Models\ChatWidgetMessage;
use App\Services\Chat\Widget\ChatWidgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Pintu PUBLIK Live Chat Widget (tanpa login), dipanggil dari iframe
 * yang dibuka public/widget.js di website klien.
 *
 * Keamanan:
 * - frame(): hanya boleh di-embed di allowed_domains (CSP frame-ancestors).
 * - API: pengunjung dikenali dari token acak (header X-Visitor-Token) yang
 *   disimpan sebagai hash -- pengunjung tidak bisa membaca chat orang lain.
 * - Rate limit per IP di routes/api.php; isi pesan dibatasi & selalu
 *   ditampilkan sebagai teks (tidak pernah HTML) di widget maupun inbox.
 */
class ChatWidgetPublicController extends Controller
{
    public function __construct(private readonly ChatWidgetService $chats)
    {
    }

    public function frame(string $key): Response
    {
        $widget = $this->activeWidget($key);

        return response()
            ->view('chat.widgets.frame', ['widget' => $widget])
            ->header('Content-Security-Policy', 'frame-ancestors '.$widget->frameAncestors());
    }

    public function session(Request $request, string $key): JsonResponse
    {
        $widget = $this->activeWidget($key);
        $token = $request->header('X-Visitor-Token');
        $isNew = ! $token || ! $this->chats->findByToken($widget, $token);
        $requireContact = $isNew && $widget->setting('require_contact');

        $validated = $request->validate([
            'name' => [$requireContact ? 'required' : 'nullable', 'string', 'max:100'],
            'phone' => [$requireContact ? 'required' : 'nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s]{6,30}$/'],
            'page_url' => ['nullable', 'string', 'max:500'],
        ]);

        [$conversation, $newToken] = $this->chats->resume($widget, $token, $validated + ['ip' => $request->ip()]);

        $host = parse_url((string) ($validated['page_url'] ?? ''), PHP_URL_HOST);
        $widget->forceFill(['last_seen_at' => now(), 'last_seen_domain' => $host ?: $widget->last_seen_domain])->saveQuietly();

        return response()->json([
            'token' => $newToken,
            'status' => $conversation->status,
            'messages' => $this->payload($conversation),
        ]);
    }

    public function messages(Request $request, string $key): JsonResponse
    {
        $conversation = $this->visitorConversation($request, $key);

        return response()->json([
            'status' => $conversation->status,
            'messages' => $this->payload($conversation, (int) $request->query('after', 0)),
        ]);
    }

    public function send(Request $request, string $key): JsonResponse
    {
        $conversation = $this->visitorConversation($request, $key);
        $validated = $request->validate(['body' => ['required', 'string', 'max:1000']]);

        $message = $this->chats->visitorMessage($conversation, trim($validated['body']));

        return response()->json(['message' => $message->toPayload()]);
    }

    public function handover(Request $request, string $key): JsonResponse
    {
        $conversation = $this->visitorConversation($request, $key);
        $this->chats->handover($conversation);

        return response()->json(['status' => $conversation->fresh()->status]);
    }

    private function activeWidget(string $key): ChatWidget
    {
        $widget = ChatWidget::where('public_key', $key)->first();

        abort_unless($widget?->isActive(), 404);

        return $widget;
    }

    private function visitorConversation(Request $request, string $key): ChatWidgetConversation
    {
        $widget = $this->activeWidget($key);
        $conversation = $this->chats->findByToken($widget, (string) $request->header('X-Visitor-Token'));

        abort_unless($conversation, 401);

        return $conversation->setRelation('widget', $widget);
    }

    private function payload(ChatWidgetConversation $conversation, int $afterId = 0): array
    {
        // Pertama kali dibuka: 100 pesan TERAKHIR; polling: pesan setelah $afterId.
        return ChatWidgetMessage::with('user:id,name')
            ->where('chat_widget_conversation_id', $conversation->id)
            ->where('id', '>', $afterId)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->map->toPayload()
            ->values()
            ->all();
    }
}
