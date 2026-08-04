<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaMessageQuickReply ("Balasan Cepat") — canned
     * responses an agent can insert into the message box. message_content
     * was added to the original spec (title + category only): a quick
     * reply needs actual content to insert. shortcut was also added —
     * the inbox's message input already has a "/ for quick reply"
     * placeholder from earlier work, implying a typed shortcut is meant
     * to trigger insertion; without a shortcut column that can't work.
     * status was added for the same reason every other feature here has
     * one: being able to disable a quick reply without deleting it.
     */
    public function up(): void
    {
        Schema::create('wa_message_quick_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('device_id', 36);

            $table->string('title');
            $table->string('shortcut', 50)->nullable();

            // text | location
            $table->string('category', 20);
            $table->text('message_content');

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_quick_replies');
    }
};
