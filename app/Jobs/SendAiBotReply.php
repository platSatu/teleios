<?php

namespace App\Jobs;

use App\Exceptions\PackageLimitExceededException;
use App\Models\WaAiBot;
use App\Services\AiBot\AiReplyGenerator;
use App\Services\Chat\BroadcastThrottleService;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\PackageLimitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one AI-generated reply — dispatched by
 * App\Http\Controllers\Api\WaIncomingMessageWebhookController as the
 * final fallback, only once no "Auto Reply (Kata Kunci)" rule (including
 * the device's default rule) matched the incoming message. Structured
 * exactly like App\Jobs\SendAutoReplyMessage (ShouldQueue, retry/
 * backoff, system JWT mint, persisted last_error, and the same
 * per-device BroadcastThrottleService ceiling — see that job's docblock
 * for why this must never sail through unthrottled just because it's an
 * AI reply rather than a broadcast) but calls out to
 * App\Services\AiBot\AiReplyGenerator instead of sending a fixed
 * template.
 */
class SendAiBotReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    /** Same reasoning as App\Jobs\SendScheduledWaMessage's identical constant. */
    private const MAX_THROTTLE_REDISPATCHES = 30;

    public function __construct(
        protected string $aiBotId,
        protected string $chatJid,
        protected string $incomingBody,
        protected int $throttleAttempts = 0,
    ) {
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox, AiReplyGenerator $generator, BroadcastThrottleService $throttle, PackageLimitService $packageLimits): void
    {
        $bot = WaAiBot::with(['company.user', 'provider', 'model'])->find($this->aiBotId);

        // Re-checked here, not just at dispatch time — the owner could
        // have switched the bot off, or its activation window could have
        // expired, in the time it sat on the queue.
        if (! $bot || ! $bot->isCurrentlyActive()) {
            return;
        }

        $owner = $bot->company?->user;

        if (! $owner) {
            $this->markFailed($bot, 'Perusahaan pemilik AI Bot ini tidak memiliki user pemilik yang valid.');

            return;
        }

        // Billing guard — see App\Services\PackageLimitService::
        // requireActivePackage()'s docblock for why this is a separate
        // check from anything broadcast-quota-related: this job runs
        // outside any HTTP request, so App\Http\Middleware\
        // EnsureActivePackage never gets a chance to block an AI reply
        // going out for a company whose package has since expired.
        if ($bot->company) {
            try {
                $packageLimits->requireActivePackage($bot->company);
            } catch (PackageLimitExceededException $e) {
                $this->markFailed($bot, $e->getMessage());

                return;
            }
        }

        // Anti-ban guard: same per-device ceiling App\Jobs\
        // SendScheduledWaMessage enforces, checked right before the send.
        // Not a failure: if the device is simply busy, this job
        // re-dispatches itself with a delay instead of touching $bot at
        // all, up to MAX_THROTTLE_REDISPATCHES times before giving up.
        if (! $throttle->attempt($bot->device_id, $bot->company_id)) {
            if ($this->throttleAttempts >= self::MAX_THROTTLE_REDISPATCHES) {
                $this->markFailed($bot, 'Perangkat pengirim terlalu sibuk (batas kirim per menit tercapai berulang kali) — balasan AI tidak jadi terkirim.');

                return;
            }

            $delaySeconds = max(5, $throttle->availableInSeconds($bot->device_id));

            Log::info('SendAiBotReply: device at broadcast rate limit, re-queueing', [
                'ai_bot_id' => $bot->id,
                'device_id' => $bot->device_id,
                'chat_jid' => $this->chatJid,
                'throttle_attempt' => $this->throttleAttempts + 1,
                'retry_in_seconds' => $delaySeconds,
            ]);

            self::dispatch($this->aiBotId, $this->chatJid, $this->incomingBody, $this->throttleAttempts + 1)
                ->delay(now()->addSeconds($delaySeconds));

            return;
        }

        try {
            $token = $jwtService->mintFor($owner);

            $history = $this->recentHistory($token, $inbox, $bot->device_id, $this->chatJid);

            $reply = $generator->generate($bot, $this->incomingBody, $history);

            $inbox->send($token, $bot->device_id, $this->chatJid, $reply);

            $bot->forceFill([
                'trigger_count' => $bot->trigger_count + 1,
                'last_triggered_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $e) {
            Log::warning('SendAiBotReply: send failed', [
                'ai_bot_id' => $bot->id,
                'device_id' => $bot->device_id,
                'chat_jid' => $this->chatJid,
                'error' => $e->getMessage(),
            ]);

            $this->markFailed($bot, $e->getMessage());

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        WaAiBot::whereKey($this->aiBotId)->update([
            'last_error' => $e->getMessage(),
        ]);
    }

    protected function markFailed(WaAiBot $bot, string $reason): void
    {
        $bot->forceFill(['last_error' => $reason])->save();
    }

    /**
     * Last few turns of the conversation, oldest-first, so the AI has
     * context instead of answering the incoming message as if it were
     * the very first thing ever said. Best-effort only: if the Go
     * backend can't be reached for history, the reply still goes out,
     * just without conversational memory — this must never block the
     * reply itself.
     *
     * @return array<int, array{role: string, text: string}>
     */
    protected function recentHistory(string $token, InboxService $inbox, string $deviceId, string $chatJid): array
    {
        try {
            $messages = $inbox->messages($token, $deviceId, $chatJid);
        } catch (Throwable $e) {
            return [];
        }

        return collect($messages)
            ->sortBy('id')
            ->slice(-10)
            ->map(fn (array $message) => [
                'role' => ! empty($message['from_me']) ? 'assistant' : 'user',
                'text' => trim((string) ($message['body'] ?? '')),
            ])
            ->filter(fn (array $turn) => $turn['text'] !== '')
            ->values()
            ->all();
    }
}
