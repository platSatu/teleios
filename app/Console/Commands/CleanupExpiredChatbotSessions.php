<?php

namespace App\Console\Commands;

use App\Models\WaChatbotFlow;
use App\Models\WaChatbotState;
use Illuminate\Console\Command;

/**
 * Runs periodically (see bootstrap/app.php's ->withSchedule()) — deletes
 * every App\Models\WaChatbotState whose last_interaction_at is older than
 * its own flow's session_timeout_minutes. App\Services\Chat\
 * ChatbotFlowService::activeState() already self-expires a stale session
 * lazily the next time that exact chat messages in again, so this command
 * is purely hygiene: a customer who starts a flow and simply never
 * replies again would otherwise leave a dead row behind forever with
 * nothing to ever trigger its lazy cleanup. Kept as a flat, bounded sweep
 * (not a per-row PHP loop) the same way App\Console\Commands\
 * EvaluateChatSlaBreaches keeps its own periodic job cheap regardless of
 * how many sessions/conversations exist.
 *
 * Grouped by each session's OWN flow timeout (not one global cutoff)
 * since different flows can configure different session_timeout_minutes
 * — a single `WHERE last_interaction_at < now() - X` wouldn't respect
 * that per-flow setting.
 */
class CleanupExpiredChatbotSessions extends Command
{
    protected $signature = 'chatbot:cleanup-expired-sessions';

    protected $description = 'Delete WhatsApp chatbot flow sessions that have been idle past their flow\'s own timeout';

    public function handle(): int
    {
        $deleted = 0;

        // Small table by design (see WaChatbotState migration's
        // docblock — only ever as many rows as there are customers
        // CURRENTLY mid-flow), and the number of distinct flows a
        // platform has is similarly small, so one query per flow here
        // is negligible — nothing like the per-message hot path
        // ChatbotFlowService itself runs on.
        WaChatbotFlow::query()->select(['id', 'session_timeout_minutes'])->chunkById(200, function ($flows) use (&$deleted) {
            foreach ($flows as $flow) {
                $timeoutMinutes = $flow->session_timeout_minutes ?: WaChatbotFlow::DEFAULT_SESSION_TIMEOUT_MINUTES;

                $deleted += WaChatbotState::where('wa_chatbot_flow_id', $flow->id)
                    ->where('last_interaction_at', '<', now()->subMinutes($timeoutMinutes))
                    ->delete();
            }
        });

        $this->info("Expired chatbot sessions cleaned up: {$deleted}.");

        return self::SUCCESS;
    }
}
