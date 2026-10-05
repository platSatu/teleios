<?php

namespace App\Jobs;

use App\Models\ChatWidgetConversation;
use App\Models\ChatWidgetMessage;
use App\Models\JadwalReminderSetting;
use App\Services\AiBot\AiReplyGenerator;
use App\Services\Chat\Widget\ChatWidgetService;
use App\Services\PackageLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Balasan AI untuk Live Chat Widget -- pasangan App\Jobs\SendAiBotReply
 * (WA), memakai AiReplyGenerator & AI Bot cabang yang sama.
 *
 * - Satu percakapan dibalas satu per satu (lock per percakapan), dan hanya
 *   pesan pengunjung TERAKHIR yang dijawab: pesan beruntun dijawab sekali
 *   dengan riwayat lengkap, tidak dobel / tidak lompat urutan.
 * - Semua data (AI Bot, riwayat) diambil dari percakapan ini sendiri, jadi
 *   tidak pernah tertukar antar pengunjung / cabang / company.
 * - Error sementara provider (503, timeout) dicoba ulang 2x (5s, 15s);
 *   baru dioper ke CS kalau tetap gagal atau errornya permanen.
 */
class SendChatWidgetAiReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** Jeda coba ulang (detik) untuk error sementara provider AI. */
    private const RETRY_DELAYS = [5, 15];

    public function __construct(
        protected string $conversationId,
        protected int $visitorMessageId,
        protected int $attempt = 0,
        protected int $retries = 0,
    ) {
        $this->onQueue(config('queue.ai_queue', 'default'));
    }

    public function handle(AiReplyGenerator $generator, ChatWidgetService $chats, PackageLimitService $packageLimits): void
    {
        $conversation = ChatWidgetConversation::with('widget.company', 'widget.branchOffice')->find($this->conversationId);

        // CS sudah mengambil alih / ditutup selagi job menunggu di antrean.
        if (! $conversation || $conversation->status !== ChatWidgetConversation::STATUS_AI) {
            return;
        }

        // Satu balasan per percakapan dalam satu waktu (pesan beruntun menunggu).
        $lock = Cache::lock("chat-widget-ai:{$conversation->id}", 120);

        if (! $lock->get()) {
            if ($this->attempt < 10) {
                self::dispatch($this->conversationId, $this->visitorMessageId, $this->attempt + 1, $this->retries)->delay(now()->addSeconds(5));
            }

            return;
        }

        try {
            $widget = $conversation->widget;
            $bot = $chats->aiBotFor($widget);

            if (! $bot) {
                $chats->handover($conversation);

                return;
            }

            $packageLimits->requireActivePackage($widget->company, $widget->branchOffice, JadwalReminderSetting::CHAT_CATEGORY_NAMES);

            $messages = ChatWidgetMessage::where('chat_widget_conversation_id', $conversation->id)
                ->whereIn('sender', [ChatWidgetMessage::SENDER_VISITOR, ChatWidgetMessage::SENDER_AI, ChatWidgetMessage::SENDER_AGENT])
                ->where('id', '<=', $this->visitorMessageId)
                ->orderByDesc('id')
                ->limit(11)
                ->get()
                ->reverse()
                ->values();

            $question = $messages->pop();

            // Sudah ada pesan pengunjung yang lebih baru: job pesan itu yang
            // menjawab (dengan riwayat yang mencakup pesan ini juga).
            $hasNewer = ChatWidgetMessage::where('chat_widget_conversation_id', $conversation->id)
                ->where('sender', ChatWidgetMessage::SENDER_VISITOR)
                ->where('id', '>', $this->visitorMessageId)
                ->exists();

            if (! $question || $question->id !== $this->visitorMessageId || $hasNewer) {
                return;
            }

            $history = $messages->map(fn (ChatWidgetMessage $message) => [
                'role' => $message->sender === ChatWidgetMessage::SENDER_VISITOR ? 'user' : 'assistant',
                'text' => $message->body,
            ])->all();

            $reply = $generator->generate($bot, $question->body, $history);

            // Cek ulang: CS bisa saja mengambil alih selama AI berpikir.
            if ($conversation->fresh()?->status === ChatWidgetConversation::STATUS_AI) {
                $chats->addMessage($conversation, ChatWidgetMessage::SENDER_AI, $reply);
            }
        } catch (Throwable $e) {
            if (AiReplyGenerator::isTransient($e) && isset(self::RETRY_DELAYS[$this->retries])) {
                Log::info('chat-widget-ai: provider sibuk, dicoba ulang', ['conversation_id' => $this->conversationId, 'retry' => $this->retries + 1]);
                self::dispatch($this->conversationId, $this->visitorMessageId, $this->attempt, $this->retries + 1)
                    ->delay(now()->addSeconds(self::RETRY_DELAYS[$this->retries]));

                return;
            }

            Log::warning('chat-widget-ai: gagal membalas, dioper ke CS', ['conversation_id' => $this->conversationId, 'error' => $e->getMessage()]);
            $chats->handover($conversation);
        } finally {
            $lock->release();
        }
    }
}
