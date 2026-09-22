<?php

namespace App\Jobs;

use App\Exceptions\PackageLimitExceededException;
use App\Models\Company;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the message(s) App\Services\Chat\ChatbotFlowService computed for
 * one step of a flow — dispatched by App\Http\Controllers\Api\
 * WaIncomingMessageWebhookController right after the flow engine runs,
 * same "engine decides synchronously, a queued job does the actual
 * WhatsApp send" split App\Jobs\SendAutoReplyMessage already uses for
 * keyword rules.
 *
 * $messages is usually just one string, but a step chain that passes
 * through several 'action' steps carrying their own message before
 * reaching one that waits can produce more than one — sent in order,
 * with a short pause between each so a burst of bot replies doesn't read
 * as an obvious automated dump landing all at once.
 */
class SendChatbotFlowMessages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    /**
     * @param  array<int, string>  $messages
     */
    public function __construct(
        protected string $companyId,
        protected string $deviceId,
        protected string $chatJid,
        protected array $messages,
    ) {
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox): void
    {
        if (empty($this->messages)) {
            return;
        }

        $company = Company::find($this->companyId);
        $owner = $company?->user;

        if (! $owner) {
            Log::warning('chatbot-flow: cannot send, company has no owner user', [
                'company_id' => $this->companyId,
                'device_id' => $this->deviceId,
            ]);

            return;
        }

        try {
            $token = $jwtService->mintFor($owner);

            foreach ($this->messages as $index => $body) {
                if ($index > 0) {
                    // A brief, deliberate pause between consecutive bot
                    // messages — mirrors how a human typing several
                    // replies in a row would naturally stagger them,
                    // rather than looking like an obvious automated dump
                    // landing all at once.
                    sleep(1);
                }

                // $company passed through so this finally gets the same
                // requireActivePackage() guard every other outbound WA
                // path has (CLAUDE.md checklist item #3/9.5 — this job
                // previously had ZERO protection at all, a company whose
                // package expired kept getting chatbot-flow replies sent
                // forever). $limitMetric explicitly null, same reasoning
                // as SendAutoReplyMessage/SendAiBotReply: a chatbot-flow
                // reply is triggered by an incoming message the same way
                // auto-reply/AI bot are, not a company-initiated broadcast
                // — whether it should ever count against broadcast_send
                // quota is the same open decision as checklist item #8,
                // not something to decide silently here.
                $inbox->send($token, $this->deviceId, $this->chatJid, $body, $company, null);
            }
        } catch (PackageLimitExceededException $e) {
            // Company's package expired mid-flow (or between steps) —
            // not a transient send failure, so logged distinctly and NOT
            // rethrown: rethrowing would trigger this job's normal
            // tries/backoff retry, but retrying an expired-package send
            // will never succeed on its own, so there's nothing a retry
            // would fix.
            Log::info('chatbot-flow: skipped, package/quota guard blocked send', [
                'company_id' => $this->companyId,
                'device_id' => $this->deviceId,
                'chat_jid' => $this->chatJid,
                'reason' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            Log::warning('chatbot-flow: SendChatbotFlowMessages failed', [
                'company_id' => $this->companyId,
                'device_id' => $this->deviceId,
                'chat_jid' => $this->chatJid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
