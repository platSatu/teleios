<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Label" catalog for the Inbox detail panel's LABELS section (see
 * resources/views/chat/inbox/inbox.blade.php) — a company defines its
 * own small set of labels (e.g. "Prospek", "VIP", "Sudah Bayar") with a
 * color, then tags individual chats with them from the chat UI. Managed
 * from Chat > Pengaturan > Label (App\Http\Controllers\Chat\
 * ChatLabelController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_chat_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name', 100);

            // Hex color (e.g. "#16a34a") the label's chip renders with in
            // the chat list/detail panel — free-form rather than a fixed
            // palette, so a company isn't stuck with only our color picks.
            $table->string('color', 7)->default('#6b7280');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_chat_labels');
    }
};
