<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaMessageScheduleLog — one row per (recipient,
     * calendar day) a "Pesan Terjadwal" schedule was due to send to.
     * Replaces the old single sent_at/attempts/last_error columns that
     * used to live directly on wa_message_schedules, which stopped being
     * enough the moment a schedule could both recur daily (date_start..
     * date_end) AND fan out to several recipients at once — a single
     * "has this been sent" flag can't represent "sent to recipient A
     * today but recipient B's number bounced".
     *
     * `recipient_key` mirrors one entry from wa_message_schedules.
     * recipients (e.g. "phone:6281234567890", "group:123...@g.us",
     * "user:<uuid>") rather than a foreign key, since recipients live in
     * that JSON column, not their own table (see the schedules migration
     * for why). The unique index on (wa_message_schedule_id,
     * recipient_key, send_date) is what actually prevents the dispatcher
     * from ever sending the same recipient twice on the same day, even
     * if it somehow runs the lookup more than once before a job
     * finishes.
     */
    public function up(): void
    {
        Schema::create('wa_message_schedule_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wa_message_schedule_id')
                ->constrained('wa_message_schedules')
                ->cascadeOnDelete();

            // Capped at 191 (not the default 255) so this column stays
            // safely combinable with wa_message_schedule_id + send_date
            // in one unique index under older utf8mb4 InnoDB key-length
            // limits — the same 191 convention Laravel itself used to
            // default every string() column to for exactly this reason.
            $table->string('recipient_key', 191);
            $table->date('send_date');

            $table->string('status', 20)->default('pending'); // pending | sent | failed
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamps();

            $table->unique(['wa_message_schedule_id', 'recipient_key', 'send_date'], 'wa_message_schedule_logs_unique_per_day');
            $table->index(['wa_message_schedule_id', 'send_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_schedule_logs');
    }
};
