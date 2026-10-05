<?php

namespace App\Services\Chat\Widget;

use App\Jobs\SendChatWidgetAiReply;
use App\Models\ChatWidget;
use App\Models\ChatWidgetConversation;
use App\Models\ChatWidgetMessage;
use App\Models\User;
use App\Models\WaAiBot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Semua aturan percakapan Live Chat Widget di satu tempat -- dipakai
 * pintu publik (ChatWidgetPublicController, pengunjung website) dan
 * dashboard CS (ChatWidgetInboxController).
 *
 * Status: ai (dijawab AI) -> waiting (pengunjung minta CS / AI tidak
 * tersedia) -> agent (diambil alih CS, AI berhenti) -> closed. Pesan baru
 * di percakapan closed membukanya lagi sebagai ai.
 */
class ChatWidgetService
{
    /**
     * Frasa "minta bicara dengan orang". Sengaja frasa, bukan kata "admin"
     * saja -- "biaya admin berapa?" itu pertanyaan biasa, bukan minta CS.
     */
    private const HANDOVER_WORDS = ['cs', 'customer service', 'operator', 'bicara dengan tim', 'bicara dengan orang', 'ngobrol sama orang', 'hubungi admin', 'chat admin', 'sama manusia', 'dengan manusia'];

    /**
     * Lanjutkan sesi dari token pengunjung, atau buat sesi baru.
     *
     * @return array{0: ChatWidgetConversation, 1: ?string} token polos hanya dikembalikan saat sesi baru dibuat
     */
    public function resume(ChatWidget $widget, ?string $token, array $visitor): array
    {
        if ($token && $conversation = $this->findByToken($widget, $token)) {
            return [$conversation, null];
        }

        $token = Str::random(48);

        $conversation = ChatWidgetConversation::create([
            'chat_widget_id' => $widget->id,
            'visitor_token_hash' => hash('sha256', $token),
            'visitor_name' => $visitor['name'] ?? null,
            'visitor_phone' => $visitor['phone'] ?? null,
            'page_url' => $visitor['page_url'] ?? null,
            'ip_address' => $visitor['ip'] ?? null,
            'status' => ChatWidgetConversation::STATUS_AI,
            'last_message_at' => now(),
        ]);

        $this->addMessage($conversation, ChatWidgetMessage::SENDER_SYSTEM, $widget->setting('greeting'));

        return [$conversation, $token];
    }

    public function findByToken(ChatWidget $widget, string $token): ?ChatWidgetConversation
    {
        return ChatWidgetConversation::where('chat_widget_id', $widget->id)
            ->where('visitor_token_hash', hash('sha256', $token))
            ->first();
    }

    /** Pesan dari pengunjung; AI dipanggil lewat antrean kalau percakapan masih di tangan AI. */
    public function visitorMessage(ChatWidgetConversation $conversation, string $body): ChatWidgetMessage
    {
        $message = DB::transaction(function () use ($conversation, $body) {
            $locked = ChatWidgetConversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === ChatWidgetConversation::STATUS_CLOSED) {
                $locked->update(['status' => ChatWidgetConversation::STATUS_AI, 'assigned_to' => null]);
            }

            $conversation->setRawAttributes($locked->getAttributes(), true);

            return $this->addMessage($locked, ChatWidgetMessage::SENDER_VISITOR, $body);
        });

        if ($conversation->status !== ChatWidgetConversation::STATUS_AI) {
            return $message;
        }

        if ($this->wantsHuman($body)) {
            $this->handover($conversation);
        } elseif ($this->aiBotFor($conversation->widget)) {
            SendChatWidgetAiReply::dispatch($conversation->id, $message->id);
        } else {
            $this->handover($conversation);
        }

        return $message;
    }

    /** Pengunjung minta CS (tombol / kata kunci) atau AI tidak tersedia. */
    public function handover(ChatWidgetConversation $conversation): void
    {
        $changed = ChatWidgetConversation::whereKey($conversation->id)
            ->where('status', ChatWidgetConversation::STATUS_AI)
            ->update(['status' => ChatWidgetConversation::STATUS_WAITING, 'updated_at' => now()]);

        if ($changed) {
            $this->addMessage($conversation, ChatWidgetMessage::SENDER_SYSTEM, 'Baik, tim kami akan segera membalas di sini. Mohon tunggu sebentar ya.');
        }
    }

    /** CS mengambil alih: AI berhenti membalas percakapan ini. */
    public function takeOver(ChatWidgetConversation $conversation, User $agent): void
    {
        $conversation->update([
            'status' => ChatWidgetConversation::STATUS_AGENT,
            'assigned_to' => $agent->id,
        ]);
    }

    public function agentReply(ChatWidgetConversation $conversation, User $agent, string $body): ChatWidgetMessage
    {
        if ($conversation->status !== ChatWidgetConversation::STATUS_AGENT) {
            $this->takeOver($conversation, $agent);
        }

        return $this->addMessage($conversation, ChatWidgetMessage::SENDER_AGENT, $body, $agent->id);
    }

    public function close(ChatWidgetConversation $conversation): void
    {
        $conversation->update(['status' => ChatWidgetConversation::STATUS_CLOSED]);
        $this->addMessage($conversation, ChatWidgetMessage::SENDER_SYSTEM, 'Percakapan ditutup. Kirim pesan lagi kapan saja kalau masih ada yang ingin ditanyakan.');
    }

    /**
     * AI Bot yang menjawab widget ini: AI milik cabang yang sama (aturan
     * 1 AI per cabang), harus aktif, dan AI widget dinyalakan.
     */
    public function aiBotFor(ChatWidget $widget): ?WaAiBot
    {
        if (! $widget->ai_enabled) {
            return null;
        }

        $bot = WaAiBot::with(['provider', 'model', 'company'])
            ->where('company_id', $widget->company_id)
            ->where('branch_office_id', $widget->branch_office_id)
            ->first();

        return $bot?->isCurrentlyActive() ? $bot : null;
    }

    public function addMessage(ChatWidgetConversation $conversation, string $sender, string $body, ?string $userId = null): ChatWidgetMessage
    {
        $message = ChatWidgetMessage::create([
            'chat_widget_conversation_id' => $conversation->id,
            'sender' => $sender,
            'user_id' => $userId,
            'body' => $body,
        ]);

        ChatWidgetConversation::whereKey($conversation->id)->update(['last_message_at' => now()]);

        return $message;
    }

    private function wantsHuman(string $body): bool
    {
        $text = ' '.mb_strtolower(trim($body)).' ';

        foreach (self::HANDOVER_WORDS as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $text)) {
                return true;
            }
        }

        return false;
    }
}
