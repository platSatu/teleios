<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeform internal notes attached to one chat, shown in the Inbox
 * detail panel's NOTES section (see resources/views/chat/inbox/
 * inbox.blade.php) — same (device_id, chat_jid) identification pattern
 * as wa_chat_label_assignments, since a "chat" has no row of its own in
 * Laravel's database (conversations live in g_backend's MySQL tables,
 * reached only through App\Services\Chat\InboxService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_chat_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('device_id', 36);
            $table->string('chat_jid', 64);

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('note');

            $table->timestamps();

            // Every lookup this feature needs: "every note on THIS chat",
            // newest first.
            $table->index(['device_id', 'chat_jid'], 'wa_chat_notes_chat_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_chat_notes');
    }
};
