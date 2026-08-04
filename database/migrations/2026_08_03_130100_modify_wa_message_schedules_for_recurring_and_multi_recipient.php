<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two behavioural changes to "Pesan Terjadwal", both requested
     * together since the second only makes sense once the first exists:
     *
     * 1. Recurring send. `schedule_date` (a single date) is replaced by
     *    `date_start`/`date_end` — the schedule now fires once a day, at
     *    `schedule_time`, on every date in that (inclusive) range, not
     *    just once. A schedule created before this migration only ever
     *    had one date, so date_start = date_end = the old schedule_date
     *    for every existing row (see the backfill below) — behaviourally
     *    identical to before until someone actually edits it to widen
     *    the range.
     *
     * 2. Multi-recipient. The old single-target model (is_group +
     *    group_jid OR phone_number) is replaced by `recipients`, a JSON
     *    array of {"type": "phone"|"group"|"user", "value": "..."}
     *    entries — a schedule can now fan out to any mix of raw phone
     *    numbers, WhatsApp groups, and this company's own users (whose
     *    phone number is resolved from `users.handphone` at send time —
     *    see App\Jobs\SendScheduledWaMessage) in one go, picked across
     *    the 3 tabs on the create/edit form. JSON (not a separate
     *    recipients table) follows the same precedent already set by
     *    wa_message_forward_campaigns.recipients.
     *
     * `sent_at`/`attempts`/`last_error` also move off this table
     * entirely: those tracked "did the one send happen yet", which
     * stops making sense once a schedule can both recur AND fan out to
     * several recipients — one recipient succeeding shouldn't block a
     * different recipient's retry, and day 2 shouldn't be blocked by
     * day 1's sent_at. That bookkeeping now lives per
     * (recipient, calendar day) in wa_message_schedule_logs — see the
     * migration that creates it.
     *
     * `use_template` / `wa_message_template_id`: lets the form's "gunakan
     * template WA" toggle pick a saved App\Models\WaMessageTemplate
     * instead of typing category_schedule + message manually — both
     * paths coexist (category_schedule/message stay for the non-template
     * path, exactly as before).
     */
    public function up(): void
    {
        Schema::table('wa_message_schedules', function (Blueprint $table) {
            $table->date('date_start')->nullable()->after('schedule_date');
            $table->date('date_end')->nullable()->after('date_start');

            $table->boolean('use_template')->default(false)->after('message');
            $table->foreignUuid('wa_message_template_id')->nullable()
                ->after('use_template')
                ->constrained('wa_message_templates')
                ->nullOnDelete();

            $table->json('recipients')->nullable()->after('phone_number');
        });

        // Backfill: every existing row only ever had one date and one
        // target — carry both forward under the new shape so nothing
        // already scheduled silently breaks or stops sending.
        DB::table('wa_message_schedules')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $recipients = [];

                if ($row->is_group && $row->group_jid) {
                    $recipients[] = ['type' => 'group', 'value' => $row->group_jid];
                } elseif ($row->phone_number) {
                    $recipients[] = ['type' => 'phone', 'value' => $row->phone_number];
                }

                DB::table('wa_message_schedules')->where('id', $row->id)->update([
                    'date_start' => $row->schedule_date,
                    'date_end' => $row->schedule_date,
                    'recipients' => json_encode($recipients),
                ]);
            }
        });

        // Now that every row has a value, date_start is safe to make
        // required at the DB level (no doctrine/dbal in this project, so
        // a raw MODIFY instead of Blueprint::change()). date_end stays
        // nullable at the DB level even though the app always fills it
        // in (defaulting it to date_start when a user leaves it blank) —
        // see MessageScheduleController's validator.
        DB::statement('ALTER TABLE wa_message_schedules MODIFY date_start DATE NOT NULL');

        Schema::table('wa_message_schedules', function (Blueprint $table) {
            $table->dropIndex('wa_message_schedules_due_lookup_index');
            $table->dropColumn(['schedule_date', 'is_group', 'group_jid', 'phone_number', 'sent_at', 'attempts', 'last_error']);

            $table->index(['status', 'date_start', 'date_end', 'schedule_time'], 'wa_message_schedules_due_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('wa_message_schedules', function (Blueprint $table) {
            $table->dropIndex('wa_message_schedules_due_lookup_index');

            $table->date('schedule_date')->nullable()->after('message');
            $table->boolean('is_group')->default(false)->after('schedule_date');
            $table->string('group_jid')->nullable()->after('is_group');
            $table->string('phone_number', 32)->nullable()->after('group_jid');

            $table->timestamp('sent_at')->nullable()->after('status');
            $table->unsignedTinyInteger('attempts')->default(0)->after('sent_at');
            $table->text('last_error')->nullable()->after('attempts');
        });

        DB::table('wa_message_schedules')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $recipients = json_decode($row->recipients ?? '[]', true) ?: [];
                $first = $recipients[0] ?? null;

                DB::table('wa_message_schedules')->where('id', $row->id)->update([
                    'schedule_date' => $row->date_start,
                    'is_group' => $first && $first['type'] === 'group',
                    'group_jid' => $first && $first['type'] === 'group' ? $first['value'] : null,
                    'phone_number' => $first && $first['type'] === 'phone' ? $first['value'] : null,
                ]);
            }
        });

        DB::statement('ALTER TABLE wa_message_schedules MODIFY schedule_date DATE NOT NULL');

        Schema::table('wa_message_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wa_message_template_id');
            $table->dropColumn(['date_start', 'date_end', 'use_template', 'recipients']);

            $table->index(['status', 'sent_at', 'schedule_date', 'schedule_time'], 'wa_message_schedules_due_lookup_index');
        });
    }
};
