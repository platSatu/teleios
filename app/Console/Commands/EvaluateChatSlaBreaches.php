<?php

namespace App\Console\Commands;

use App\Services\Chat\ConversationService;
use Illuminate\Console\Command;

/**
 * Runs every minute (see bootstrap/app.php's ->withSchedule()) — flags
 * every WaConversation whose response/resolution SLA due date has
 * passed without being met. Purely a bulk flag-setter: two flat UPDATE
 * statements via App\Services\Chat\ConversationService::
 * evaluateSlaBreaches(), so this stays fast regardless of how many
 * conversations a company (or the whole platform) has — no per-row PHP
 * loop, no N+1 queries.
 *
 * Persisting the breach flags (rather than computing "is this late" on
 * every page load) is what lets the Inbox queue/dashboard list hundreds
 * of conversations without re-evaluating SLA math per row at request
 * time — see wa_conversations.first_response_breached/resolution_breached.
 */
class EvaluateChatSlaBreaches extends Command
{
    protected $signature = 'chat:evaluate-sla';

    protected $description = 'Flag WhatsApp conversations that have breached their first-response or resolution SLA';

    public function handle(ConversationService $conversations): int
    {
        $result = $conversations->evaluateSlaBreaches();

        $this->info("First-response breaches flagged: {$result['first_response']}. Resolution breaches flagged: {$result['resolution']}.");

        return self::SUCCESS;
    }
}
