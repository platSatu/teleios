<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same bookkeeping pattern as wa_message_schedules (see
     * 2026_08_02_100000_add_execution_columns_to_wa_message_schedules_table):
     * `status` on this table stays the user's own active/inactive toggle
     * for the step; sent_at/attempts/last_error track what the execution
     * engine (App\Console\Commands\DispatchDueMessageSequenceSteps +
     * App\Jobs\SendMessageSequenceStep) actually did with it. Each row
     * here is one drip step, sent independently once its own
     * "responder.start_date + delay_days" arrives — sent_at is per-step,
     * not per-responder, since two steps of the same enrollment fire on
     * different days.
     */
    public function up(): void
    {
        Schema::table('wa_message_sequences', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->unsignedTinyInteger('attempts')->default(0)->after('sent_at');
            $table->text('last_error')->nullable()->after('attempts');

            $table->index(['status', 'sent_at'], 'wa_message_sequences_due_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_sequences', function (Blueprint $table) {
            $table->dropIndex('wa_message_sequences_due_lookup_index');
            $table->dropColumn(['sent_at', 'attempts', 'last_error']);
        });
    }
};
