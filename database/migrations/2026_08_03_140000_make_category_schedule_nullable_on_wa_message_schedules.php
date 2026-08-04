<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `category_schedule` was originally required (a schedule always had
     * a text/location category). Since the "Gunakan Template WA" toggle
     * was added, MessageScheduleController::finalize() intentionally
     * nulls this column out when use_template=true (category/message
     * only apply to the manual-message path) — but the column itself was
     * never relaxed to allow that, so every template-based schedule
     * failed with "Column 'category_schedule' cannot be null" on insert.
     * No doctrine/dbal in this project, so a raw MODIFY instead of
     * Blueprint::change().
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE wa_message_schedules MODIFY category_schedule VARCHAR(20) NULL");
    }

    public function down(): void
    {
        // Back to NOT NULL would fail on any row that's currently null
        // (every template-based schedule) — backfill a default first so
        // rolling back doesn't itself throw a constraint error.
        DB::table('wa_message_schedules')->whereNull('category_schedule')->update(['category_schedule' => 'text']);

        DB::statement("ALTER TABLE wa_message_schedules MODIFY category_schedule VARCHAR(20) NOT NULL");
    }
};
