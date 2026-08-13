<?php

namespace App\Jobs;

use App\Models\WaConversation;
use App\Models\WaCsatSurvey;
use App\Services\Chat\CsatSurveyService;
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
 * Sends one CSAT survey — dispatched by App\Services\Chat\
 * CsatSurveyService::maybeSendSurvey() the moment a conversation is
 * marked resolved. Sent as an ordinary Fitur #5 WhatsApp poll (App\
 * Services\Chat\InboxService::sendPoll()) rather than a plain text
 * message + free-text reply: a poll gives a customer a one-tap answer
 * (higher response rate than "please reply with a number") and, more
 * importantly, its vote is unambiguous to score — a free-text reply like
 * "not bad I guess" has no reliable 1-5 mapping, a poll vote always
 * resolves to exactly one of the 5 options that were sent.
 *
 * Re-checks companies.csat_enabled here (not just trusting the snapshot
 * from when CsatSurveyService queued this) — a company could disable
 * CSAT in the gap between a conversation being resolved and this job
 * actually running.
 */
class SendCsatSurvey implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [15, 60, 180];

    public function __construct(protected string $conversationId)
    {
    }

    public function handle(SystemJwtService $jwtService, InboxService $inbox): void
    {
        $conversation = WaConversation::with('company.user')->find($this->conversationId);

        if (! $conversation || ! $conversation->company) {
            return;
        }

        $company = $conversation->company;

        if (! $company->csat_enabled) {
            return;
        }

        $owner = $company->user;

        if (! $owner) {
            Log::warning('csat-survey: company has no owner user, cannot send', ['company_id' => $company->id]);

            return;
        }

        $question = $company->csat_question ?: CsatSurveyService::DEFAULT_QUESTION;
        $options = CsatSurveyService::options();

        try {
            $token = $jwtService->mintFor($owner);

            $message = $inbox->sendPoll($token, $conversation->device_id, $conversation->chat_jid, $question, $options, 1);

            WaCsatSurvey::create([
                'company_id' => $company->id,
                'branch_office_id' => $conversation->branch_office_id,
                'device_id' => $conversation->device_id,
                'chat_jid' => $conversation->chat_jid,
                'wa_conversation_id' => $conversation->id,
                'poll_message_id' => $message['message_id'] ?? '',
                'question' => $question,
                'options' => $options,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('csat-survey: SendCsatSurvey failed', [
                'conversation_id' => $conversation->id,
                'device_id' => $conversation->device_id,
                'chat_jid' => $conversation->chat_jid,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
