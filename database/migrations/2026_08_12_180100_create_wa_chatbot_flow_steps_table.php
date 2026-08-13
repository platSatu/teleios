<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaChatbotFlowStep — one node in a WaChatbotFlow's
 * conversation tree. See App\Services\Chat\ChatbotFlowService::walk() for
 * how these are actually executed; in short, four step_type values:
 *
 *   - message: sends `message`, then WAITS for any reply before moving to
 *     default_next_step_id.
 *   - choice: sends `message` plus a numbered list built from `options`,
 *     WAITS for a reply matching one of them (by number or by typing the
 *     label), and branches to that option's own next_step_id — falling
 *     back to default_next_step_id if nothing matches.
 *   - action: performs one automated action (see the ACTION_* constants
 *     on the model — assign the conversation, change its status, tag it
 *     with a label, or hand off to a human) with NO waiting, then moves
 *     straight on to default_next_step_id. Lets a flow do real CRM work,
 *     not just talk.
 *   - end: sends `message` (an optional goodbye) and terminates the
 *     session.
 *
 * Every step_type may carry a `message` — even 'action' steps, so e.g. a
 * handoff can say "Baik, tim kami akan segera membantu Anda" on its way
 * out. `options`/`action`/`action_value`/`default_next_step_id` are all
 * nullable and only meaningful for the step_type(s) that use them; kept
 * on one table (not one per type) since a flow's steps are read/rendered
 * together as a whole tree far more often than any single step is queried
 * alone — see WaChatbotFlow::steps().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_chatbot_flow_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wa_chatbot_flow_id')
                ->constrained('wa_chatbot_flows')
                ->cascadeOnDelete();

            $table->string('step_type', 20); // message | choice | action | end

            $table->text('message')->nullable();

            // For step_type = 'choice': JSON array of
            // {"label": "...", "value": "...", "next_step_id": "uuid|null"}.
            // next_step_id values are plain strings, not a real foreign
            // key (can't be — they live inside a JSON blob), so
            // App\Http\Controllers\Chat\ChatbotFlowController validates
            // every id it's given actually belongs to the same flow
            // before saving, rather than relying on the database to catch
            // a typo/cross-flow reference.
            $table->json('options')->nullable();

            // For step_type = 'action': one of WaChatbotFlowStep::ACTION_*.
            // action_value's meaning depends on which one — a specific
            // team member's user id for assign_conversation, a
            // wa_chat_labels.id for add_label, unused otherwise.
            $table->string('action', 40)->nullable();
            $table->string('action_value')->nullable();

            // Where to go next for 'message'/'action' steps, and the
            // FALLBACK for 'choice' steps when no option matches. Null
            // means "end the flow here" — a real FK (self-referencing) is
            // safe here since, unlike wa_devices, this whole table is
            // Laravel-owned with no cross-storage-engine concern.
            $table->foreignUuid('default_next_step_id')
                ->nullable()
                ->constrained('wa_chatbot_flow_steps')
                ->nullOnDelete();

            // Marks this flow's entry point — App\Services\Chat\
            // ChatbotFlowService::start() looks up the one step with
            // is_start = true rather than assuming position 0 is always
            // first, since a builder UI may reorder steps freely.
            $table->boolean('is_start')->default(false);

            // Display/ordering only in the builder UI — execution never
            // reads this, it only ever follows default_next_step_id /
            // options[].next_step_id.
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['wa_chatbot_flow_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_chatbot_flow_steps');
    }
};
