<?php

namespace App\Services\Chat;

use App\Jobs\SendCsatSurvey;
use App\Models\Company;
use App\Models\WaConversation;

/**
 * Decides whether a just-resolved App\Models\WaConversation should get a
 * CSAT (Customer Satisfaction) survey — Fitur #7. Called from
 * App\Services\Chat\ConversationService::setStatus() the moment a
 * conversation genuinely transitions INTO 'resolved' (not on every save
 * — see that method), which is the one place in the app a conversation's
 * status change is guaranteed to funnel through, whether it came from an
 * agent clicking "Tandai Selesai" in the Inbox or a Fitur #6 chatbot
 * flow's ACTION_SET_STATUS_RESOLVED step.
 *
 * This class only decides eligibility (cheap, indexed lookups); the
 * actual WhatsApp poll send is deferred to App\Jobs\SendCsatSurvey, same
 * "decide synchronously inside the triggering transaction, act
 * asynchronously via a queued job" split every other outbound-message
 * feature in this app already uses (App\Jobs\SendAutoReplyMessage,
 * SendChatbotFlowMessages, etc).
 */
class CsatSurveyService
{
    /** Used when a company hasn't set companies.csat_question. */
    public const DEFAULT_QUESTION = 'Seberapa puas Anda dengan layanan kami pada percakapan ini?';

    /**
     * The 5 options a CSAT poll is always sent with, worst to best. A
     * customer's vote only ever carries back the CHOSEN OPTION'S TEXT
     * (WhatsApp polls have no separate numeric "value" per option) — see
     * App\Http\Controllers\Api\WaPollVoteWebhookController, which scores
     * a response by finding this array's INDEX of the matched text
     * (stored per-survey in wa_csat_surveys.options, not read live from
     * here, so a later change to this list never reinterprets an
     * already-sent survey's old responses under new wording).
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return [
            '⭐ Sangat Tidak Puas',
            '⭐⭐ Kurang Puas',
            '⭐⭐⭐ Cukup Puas',
            '⭐⭐⭐⭐ Puas',
            '⭐⭐⭐⭐⭐ Sangat Puas',
        ];
    }

    /**
     * Queues a CSAT survey send for $conversation if its company has
     * opted in (companies.csat_enabled) and the chat is a type WhatsApp
     * even allows a normal message to be sent back into — channels/
     * newsletters can't receive one at all (see
     * WaIncomingMessageWebhookController's same @newsletter check), and
     * a group chat has no single "customer" whose individual
     * satisfaction the survey is meant to measure, so both are skipped.
     */
    public function maybeSendSurvey(WaConversation $conversation): void
    {
        if (! $conversation->company_id) {
            return;
        }

        if (str_ends_with($conversation->chat_jid, '@newsletter') || str_ends_with($conversation->chat_jid, '@g.us')) {
            return;
        }

        $company = Company::query()->find($conversation->company_id, ['id', 'csat_enabled']);

        if (! $company || ! $company->csat_enabled) {
            return;
        }

        SendCsatSurvey::dispatch($conversation->id);
    }
}
