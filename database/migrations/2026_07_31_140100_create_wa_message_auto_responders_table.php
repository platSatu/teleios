<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaMessageAutoResponder ("Balasan Otomatis") — one
     * row per contact enrolled into a drip sequence, starting from
     * start_date. The actual messages sent (and when, relative to
     * start_date) live in wa_message_sequences, one-to-many against
     * this table via message_auto_responder_id.
     */
    public function up(): void
    {
        Schema::create('wa_message_auto_responders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('device_id', 36);

            $table->string('phone_number', 32);
            $table->date('start_date');

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_auto_responders');
    }
};
