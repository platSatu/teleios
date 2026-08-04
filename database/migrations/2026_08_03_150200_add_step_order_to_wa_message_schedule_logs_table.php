<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A 'drip' schedule can send the SAME recipient several different
     * messages on several different days (one per
     * WaMessageScheduleStep), so "(schedule, recipient, day)" alone is no
     * longer a unique send — it needs to also carry which step. Deliberately
     * a plain NOT NULL integer (default 0 = "no step", used by 'once'/
     * 'recurring' schedules) rather than a nullable step_id: MySQL unique
     * indexes treat every NULL as distinct from every other NULL, so a
     * nullable FK here would silently stop enforcing the one-send-per-day
     * guarantee for every non-drip schedule the moment it was added.
     * `step_order` mirrors WaMessageScheduleStep.sequence_order rather
     * than being a foreign key — a step can be deleted/reordered without
     * orphaning history rows that only need to remember "this was step
     * #2", not hold a live reference to it.
     */
    public function up(): void
    {
        Schema::table('wa_message_schedule_logs', function (Blueprint $table) {
            $table->unsignedInteger('step_order')->default(0)->after('recipient_key');
        });

        Schema::table('wa_message_schedule_logs', function (Blueprint $table) {
            $table->dropUnique('wa_message_schedule_logs_unique_per_day');
            $table->unique(
                ['wa_message_schedule_id', 'recipient_key', 'send_date', 'step_order'],
                'wa_message_schedule_logs_unique_per_day'
            );
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_schedule_logs', function (Blueprint $table) {
            $table->dropUnique('wa_message_schedule_logs_unique_per_day');
            $table->unique(
                ['wa_message_schedule_id', 'recipient_key', 'send_date'],
                'wa_message_schedule_logs_unique_per_day'
            );
            $table->dropColumn('step_order');
        });
    }
};
