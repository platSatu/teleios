<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Merges what used to be 3 separate features (Pesan Terjadwal,
     * Forward & Campaign Broadcast, Balasan Otomatis) into one entity,
     * distinguished by `type`:
     *
     *   - 'once'      — single send, one calendar date+time (old Forward
     *                   & Campaign Broadcast). date_end mirrors
     *                   date_start; there's nothing to repeat.
     *   - 'recurring' — fires daily at schedule_time from date_start to
     *                   date_end (the original "Pesan Terjadwal" this
     *                   table already was — unchanged behaviour).
     *   - 'drip'      — enrolls its recipients starting date_start, then
     *                   sends each row in wa_message_schedule_steps
     *                   date_start + step.delay_days later, at
     *                   schedule_time (old "Balasan Otomatis"). The
     *                   parent row's own message/category_schedule/
     *                   use_template columns are unused for this type —
     *                   each step carries its own content instead.
     *
     * All 3 types share device_id, title, recipients (the phone/group/
     * user tri-tab), and status — that shared shape is exactly what made
     * merging them worthwhile instead of keeping 3 near-identical tables.
     */
    public function up(): void
    {
        Schema::table('wa_message_schedules', function (Blueprint $table) {
            $table->string('type', 20)->default('recurring')->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_schedules', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
