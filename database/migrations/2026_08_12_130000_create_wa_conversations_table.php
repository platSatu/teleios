<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaConversation — the "chat ops" layer on top of one
 * WhatsApp thread (device_id + chat_jid, same identity pair
 * App\Models\WaChatNote/WaChatLabelAssignment already key on, since a
 * "chat" has no row of its own in Laravel's database — see those
 * models' docblocks). This is deliberately a SEPARATE concept from
 * App\Models\WaContact:
 *
 *   - WaContact is the customer's identity (keyed by phone, company-wide,
 *     survives across devices and over time) — "who is this person and
 *     who owns the relationship".
 *   - WaConversation is one operational thread's current work state —
 *     "is someone actively handling this conversation right now, and are
 *     we inside/outside our SLA for it". A contact can go through many
 *     conversation cycles (open -> resolved -> reopened) over its
 *     lifetime; each cycle's timing is tracked here, not smeared across
 *     the contact's permanent record.
 *
 * status is a simple 3-state machine (see WaConversation::STATUS_*):
 *   open      -> waiting on an agent to act (SLA clock running)
 *   pending   -> agent already replied, now waiting on the customer
 *   resolved  -> closed; reopens automatically the moment a new inbound
 *                message arrives (see App\Services\Chat\ConversationService
 *                ::recordInbound()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Nullable for the same reason wa_devices.company_id is
            // nullable (see 2026_08_05_120000_add_company_branch_fields_
            // to_wa_devices_table.php): a device added by a standalone
            // user with no Company row at all resolves to company_id =
            // null here too. Real FK (unlike wa_devices' own company_id
            // column) since this table is entirely Laravel-owned —
            // there's no cross-storage-engine issue crossing into a
            // GORM-managed table here.
            $table->foreignUuid('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            // Not a foreignUuid: WhatsApp devices live entirely in the
            // separate Go backend's own table (see WaMessageSchedule's
            // migration for the same reasoning).
            $table->string('device_id', 36);
            $table->string('chat_jid', 64);

            // Best-effort link to the customer identity record, resolved
            // by phone at the moment this row is created/reopened — left
            // null when the sender's phone hasn't resolved yet (e.g.
            // "@lid" chats) or no App\Models\WaContact exists yet for
            // them. Never written to directly by user input.
            $table->foreignUuid('contact_id')
                ->nullable()
                ->constrained('wa_contacts')
                ->nullOnDelete();

            $table->string('status', 16)->default('open');

            // Who currently owns working this conversation — distinct
            // from wa_contacts.assigned_to (the account owner). Filled
            // in automatically by ConversationService::autoAssign() the
            // first time a conversation is created, editable afterwards
            // from the Inbox detail panel.
            $table->foreignUuid('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            // Start of the CURRENT open/pending cycle — reset to now()
            // every time a resolved conversation reopens, so a
            // conversation that was resolved a month ago and just got a
            // new message today isn't instantly reported as a month-old
            // SLA breach. useCurrent() gives MySQL an explicit
            // CURRENT_TIMESTAMP default so this NOT NULL column doesn't
            // trip strict-mode's "Invalid default value" error (MySQL
            // only auto-assigns an implicit default to the single FIRST
            // timestamp column in a CREATE TABLE, and this isn't it).
            $table->timestamp('opened_at')->useCurrent();

            // First outbound message sent while this cycle was 'open' —
            // null means the response SLA is still running (or already
            // breached, see first_response_breached below).
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('last_outbound_at')->nullable();

            // Computed once per cycle from the company's own SLA config
            // (companies.chat_sla_first_response_minutes / chat_sla_
            // resolution_minutes, see the next migration) at the moment
            // opened_at is (re)set — a later company-wide config change
            // deliberately does not retroactively rewrite an
            // already-running cycle's due dates.
            $table->timestamp('sla_first_response_due_at')->nullable();
            $table->timestamp('sla_resolution_due_at')->nullable();

            // Persisted (not computed on every read) so listing hundreds
            // of conversations never has to evaluate "is this late" per
            // row at request time — see App\Console\Commands\
            // EvaluateChatSlaBreaches, which is the only thing that ever
            // flips these, in two flat bulk UPDATEs regardless of how
            // many conversations exist.
            $table->boolean('first_response_breached')->default(false);
            $table->boolean('resolution_breached')->default(false);

            $table->timestamps();

            // One operational thread per device+chat — re-deriving an
            // existing row (rather than creating a duplicate) is exactly
            // what ConversationService::recordInbound()'s firstOrCreate
            // relies on.
            $table->unique(['device_id', 'chat_jid']);

            // "My company's open queue" (Inbox ops dashboard) and "my
            // own assigned conversations" (badge/filter) are the two
            // real query shapes this feature makes.
            $table->index(['company_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['branch_office_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_conversations');
    }
};
