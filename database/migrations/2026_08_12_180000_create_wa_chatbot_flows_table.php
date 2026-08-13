<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaChatbotFlow — Fitur #6, "Advanced Chatbot Flow
 * Builder". Deliberately built ON TOP of the existing, simpler App\
 * Models\WaMessageAutoReply ("Auto Reply (Kata Kunci)") rather than
 * replacing it: a keyword rule still answers in one shot, a flow is for
 * when a company needs a genuine multi-step conversation (collect an
 * answer, branch on it, take an action, keep going) — e.g. a booking
 * wizard, a lead-qualification survey, or a support triage tree ending in
 * a human handoff.
 *
 * A flow is only ever entered through its own `trigger_keyword` (see
 * matchesTrigger()) — never as a silent fallback like WaMessageAutoReply's
 * `is_default` — so a customer always starts a flow by typing something
 * specific, and App\Http\Controllers\Api\WaIncomingMessageWebhookController
 * checks for one BEFORE the ordinary keyword-rule chain (a flow trigger is
 * the more specific/intentional match of the two).
 *
 * Once started, a customer's position inside the flow is tracked in
 * wa_chatbot_states (see that migration) — every subsequent reply from
 * them is routed through the flow engine (App\Services\Chat\
 * ChatbotFlowService) instead of the keyword chain, until the flow ends
 * (an 'end' step, a 'handoff_human' action, or the session simply times
 * out — see session_timeout_minutes below).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_chatbot_flows', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable for the same reason wa_conversations.company_id is
            // nullable — a device added by a standalone user with no
            // Company row at all resolves to company_id = null here too.
            $table->foreignUuid('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            // Not a foreignUuid: WhatsApp devices live entirely in the
            // separate Go backend's own table (see WaMessageAutoReply's
            // device_id column for the same reasoning).
            $table->string('device_id', 36);

            $table->string('name');

            // How a customer starts this flow — same contains|exact
            // matching convention as wa_message_auto_replies.match_type.
            $table->string('trigger_keyword')->nullable();
            $table->string('trigger_match_type', 20)->default('exact');

            $table->string('status', 20)->default('active'); // active | inactive

            // How long a customer's mid-flow session (wa_chatbot_states)
            // is allowed to sit idle before it's treated as abandoned and
            // silently cleared — see App\Services\Chat\ChatbotFlowService
            // ::activeState(). Without this, someone who starts a flow and
            // never finishes it would stay permanently "stuck" inside it,
            // unable to ever reach the ordinary keyword auto-reply/AI bot
            // chain again.
            $table->unsignedInteger('session_timeout_minutes')->default(30);

            $table->timestamps();

            $table->index(['device_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_chatbot_flows');
    }
};
