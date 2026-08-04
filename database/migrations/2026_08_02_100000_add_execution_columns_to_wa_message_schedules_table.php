<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the bookkeeping columns the execution engine needs
     * (App\Console\Commands\DispatchDueWaMessageSchedules +
     * App\Jobs\SendScheduledWaMessage). Deliberately separate from
     * `status` (active|inactive), which stays the user-facing
     * pause/resume toggle on the "Pesan Terjadwal" form — these three
     * columns instead track what actually happened when the schedule's
     * due date/time arrived:
     *
     * - sent_at: null until a send genuinely succeeds. This is the flag
     *   the dispatcher command filters on (whereNull('sent_at')) so a
     *   schedule is never sent twice, and it's set inside the job's own
     *   DB transaction (with a row lock) right after a successful send —
     *   not optimistically beforehand — so a failed send leaves it null
     *   and eligible for retry.
     * - attempts: incremented on every job run (success or failure), so
     *   a schedule that keeps failing (e.g. device disconnected) can be
     *   capped out rather than retried forever by the dispatcher.
     * - last_error: the most recent failure message, surfaced in the
     *   "Pesan Terjadwal" list so a company owner can see *why* something
     *   didn't go out instead of it just silently sitting there.
     */
    public function up(): void
    {
        Schema::table('wa_message_schedules', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->unsignedTinyInteger('attempts')->default(0)->after('sent_at');
            $table->text('last_error')->nullable()->after('attempts');

            // The dispatcher's due-schedule lookup filters/sorts on
            // exactly these columns every minute — an index keeps that
            // query cheap as the table grows instead of a full scan.
            $table->index(['status', 'sent_at', 'schedule_date', 'schedule_time'], 'wa_message_schedules_due_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_schedules', function (Blueprint $table) {
            $table->dropIndex('wa_message_schedules_due_lookup_index');
            $table->dropColumn(['sent_at', 'attempts', 'last_error']);
        });
    }
};
