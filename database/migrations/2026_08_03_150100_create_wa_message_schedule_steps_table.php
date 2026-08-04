<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaMessageScheduleStep — one message in a 'drip'
     * type WaMessageSchedule (see the migration that added `type`).
     * Mirrors what WaMessageAutoResponder's old wa_message_sequences
     * table carried per step (sequence_order, delay_days, content),
     * minus `device_id`/`sent_at`/`attempts`/`last_error` — device now
     * lives once on the parent schedule (every step in one drip always
     * sends from the same device), and per-send bookkeeping lives in
     * wa_message_schedule_logs keyed by (schedule, recipient, day, step)
     * instead, same as every other type.
     *
     * `use_template`/`wa_message_template_id`/`category_schedule`/
     * `message` intentionally mirror the same 4 columns on
     * wa_message_schedules itself — each step picks its own content the
     * same way the parent does for 'once'/'recurring' schedules.
     */
    public function up(): void
    {
        Schema::create('wa_message_schedule_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wa_message_schedule_id')
                ->constrained('wa_message_schedules')
                ->cascadeOnDelete();

            $table->unsignedInteger('sequence_order')->default(1);
            $table->unsignedInteger('delay_days')->default(0);

            $table->boolean('use_template')->default(false);
            $table->foreignUuid('wa_message_template_id')->nullable()
                ->constrained('wa_message_templates')
                ->nullOnDelete();
            $table->string('category_schedule', 20)->nullable();
            $table->text('message')->nullable();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            // Explicit short name — MySQL's default auto-generated name
            // for this pair of columns (~72 chars) exceeds its 64-char
            // identifier limit.
            $table->index(['wa_message_schedule_id', 'sequence_order'], 'wa_msg_schedule_steps_schedule_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_schedule_steps');
    }
};
