<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaMessageSequence — the individual steps of one
     * wa_message_auto_responders enrollment ("kirim pesan X, Y hari
     * setelah start_date"). sequence_order and delay_days were added to
     * the original spec: without an order and a delay, a "sequence"
     * has no way to know what to send when — every other field
     * (message_type, message_content, status) was already specified.
     */
    public function up(): void
    {
        Schema::create('wa_message_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('message_auto_responder_id')
                ->constrained('wa_message_auto_responders')
                ->cascadeOnDelete();

            $table->string('device_id', 36);

            // Where this step falls in the sequence, and how many days
            // after the enrollment's start_date it should fire.
            $table->unsignedInteger('sequence_order')->default(1);
            $table->unsignedInteger('delay_days')->default(0);

            // text | location | button | template
            $table->string('message_type', 20);
            $table->text('message_content');

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_sequences');
    }
};
