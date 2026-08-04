<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaMessageAutoReply — keyword-triggered auto
     * replies. reply_message and match_type were added to the original
     * spec (keyword + status only): a keyword with nothing to reply
     * with can't actually do anything.
     */
    public function up(): void
    {
        Schema::create('wa_message_auto_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('device_id', 36);

            $table->string('keyword');

            // contains | exact — how `keyword` is matched against an
            // incoming message's text.
            $table->string('match_type', 20)->default('contains');

            $table->text('reply_message');

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_auto_replies');
    }
};
