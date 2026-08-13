<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaCsatSurvey — Fitur #7. One row per CSAT poll sent to
 * an end customer (see App\Services\Chat\CsatSurveyService::
 * maybeSendSurvey(), fired by App\Services\Chat\ConversationService::
 * setStatus() the moment a conversation is genuinely marked resolved —
 * whether by an agent from the Inbox or by a Fitur #6 chatbot flow's
 * ACTION_SET_STATUS_RESOLVED step).
 *
 * The survey itself is sent as an ordinary Fitur #5 WhatsApp poll
 * (App\Services\Chat\InboxService::sendPoll()), reusing that already-
 * anti-ban-safe interactive message type rather than inventing a new
 * send mechanism. `question`/`options` are a SNAPSHOT of what was
 * actually sent (not a live reference to companies.csat_question) — a
 * company editing their survey question later must never rewrite the
 * wording of surveys that already went out.
 *
 * `score`/`selected_option`/`responded_at` start null and are filled in
 * later by App\Http\Controllers\Api\WaPollVoteWebhookController when
 * g_backend's WaInboxService.notifyPollVoteWebhook reports the matching
 * poll_message_id got a vote — see that controller for how `options`
 * (this row's own snapshot) is used to translate the voter's chosen text
 * back into a 1-5 score.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_csat_surveys', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable for the same reason every other wa_* table's
            // company_id is nullable (see wa_conversations' migration).
            $table->foreignUuid('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            // Not foreignUuid: WhatsApp devices live entirely in the
            // separate Go backend's own table — same reasoning as every
            // other wa_* table keyed on a bare device_id string.
            $table->string('device_id', 36);
            $table->string('chat_jid', 64);

            // The conversation this survey was sent for — kept nullable
            // (nullOnDelete, not cascade) so a company deleting old
            // resolved conversations for cleanup doesn't silently erase
            // CSAT history that's meant to be a durable satisfaction
            // record, independent of how long the underlying thread
            // itself is retained.
            $table->foreignUuid('wa_conversation_id')
                ->nullable()
                ->constrained('wa_conversations')
                ->nullOnDelete();

            // WhatsApp's own message id for the poll that was sent —
            // what App\Http\Controllers\Api\WaPollVoteWebhookController
            // matches an incoming vote against. Not globally unique on
            // its own (WhatsApp message ids are only unique per device),
            // hence the composite unique index below.
            $table->string('poll_message_id', 64);

            $table->string('question', 255);

            // Snapshot of the exact option strings sent, in order — see
            // WaPollVoteWebhookController: the voted-for option's INDEX
            // in this array is the 1-5 score, since a WhatsApp poll vote
            // only ever carries back the chosen option's text, never a
            // separate numeric value.
            $table->json('options');

            $table->timestamp('sent_at')->useCurrent();

            $table->unsignedTinyInteger('score')->nullable();
            $table->string('selected_option')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->timestamps();

            $table->unique(['device_id', 'poll_message_id']);

            // "This company's CSAT trend over a date range" — the
            // reporting query shape App\Services\Chat\ChatReportingService
            // ::csatSummary() runs.
            $table->index(['company_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_csat_surveys');
    }
};
