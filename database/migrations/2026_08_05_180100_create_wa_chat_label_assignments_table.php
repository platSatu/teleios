<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which App\Models\WaChatLabel(s) are tagged onto one chat. A "chat" has
 * no row of its own in Laravel's database at all — conversations live
 * entirely in g_backend's MySQL tables (wa_chats/wa_messages), reached
 * only through App\Services\Chat\InboxService's HTTP calls — so a chat
 * is identified here the same way the rest of this app already
 * identifies one: the (device_id, chat_jid) pair, not a foreign key.
 * company_id is denormalized onto this table (not just reachable via
 * wa_chat_label_id -> wa_chat_labels.company_id) purely so every lookup
 * this feature needs can filter by company_id directly, without an
 * extra join, on the two column combinations it's actually queried by:
 * "labels for this one chat" and "every chat tagged with this label".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_chat_label_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wa_chat_label_id')
                ->constrained('wa_chat_labels')
                ->cascadeOnDelete();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('device_id', 36);
            $table->string('chat_jid', 64);

            $table->timestamps();

            // A given label can only be attached to a given chat once —
            // clicking "+ Add" twice on the same label is a no-op, not a
            // duplicate row.
            $table->unique(['wa_chat_label_id', 'device_id', 'chat_jid'], 'wa_chat_label_assignments_unique');

            // The lookup this whole feature is built around: "which
            // labels does THIS chat have" — hit on every chat detail
            // panel open/label toggle.
            $table->index(['device_id', 'chat_jid'], 'wa_chat_label_assignments_chat_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_chat_label_assignments');
    }
};
