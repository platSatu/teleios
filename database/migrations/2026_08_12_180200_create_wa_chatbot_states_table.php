<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaChatbotState — which step of which WaChatbotFlow one
 * (device_id, chat_jid) is currently sitting at, waiting for a reply. At
 * most one active session per chat (see the unique index below): a
 * customer can only ever be "inside" one flow at a time, matching how a
 * real conversation works.
 *
 * `variables` accumulates every answer collected so far, keyed by the
 * step id that asked for it — kept here (not smeared across separate
 * columns) since a flow's shape is entirely company-defined at runtime;
 * there's no fixed set of "fields" a session always has. This is
 * primarily for potential future use (e.g. surfacing collected answers on
 * the Inbox detail panel) — the engine itself only ever reads
 * current_step_id to decide what happens next.
 *
 * Deleted (not soft-deleted/archived) the moment a flow session ends,
 * whether by reaching an 'end' step, a 'handoff_human' action, or timing
 * out — see App\Services\Chat\ChatbotFlowService. This table is meant to
 * stay small (only ever as many rows as there are customers CURRENTLY
 * mid-flow across the whole app), not an event log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_chatbot_states', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Not a foreignUuid: WhatsApp devices live entirely in the
            // separate Go backend's own table — same reasoning as every
            // other wa_* table keyed on a bare device_id string.
            $table->string('device_id', 36);
            $table->string('chat_jid', 64);

            $table->foreignUuid('wa_chatbot_flow_id')
                ->constrained('wa_chatbot_flows')
                ->cascadeOnDelete();

            $table->foreignUuid('current_step_id')
                ->nullable()
                ->constrained('wa_chatbot_flow_steps')
                ->nullOnDelete();

            $table->json('variables')->nullable();

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_interaction_at')->useCurrent();

            $table->timestamps();

            // One active flow session per chat.
            $table->unique(['device_id', 'chat_jid']);

            // Powers the idle-session cleanup sweep (see App\Console\
            // Commands\CleanupExpiredChatbotSessions) — "every session
            // whose last activity was more than N minutes ago" without a
            // full table scan.
            $table->index(['last_interaction_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_chatbot_states');
    }
};
