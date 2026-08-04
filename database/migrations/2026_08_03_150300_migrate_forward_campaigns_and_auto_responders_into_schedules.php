<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * The data half of merging "Forward & Campaign Broadcast" and
     * "Balasan Otomatis" into the unified WaMessageSchedule (see the
     * migration that added `type`) — every existing row from both
     * features is carried over as a schedule (+ steps, for drip) so
     * nothing already configured just disappears, then the 3 tables
     * those features used to own are dropped since MessageScheduleController
     * is now the only place either lives.
     */
    public function up(): void
    {
        $this->migrateForwardCampaigns();
        $this->migrateAutoResponders();

        // Child-of-child first: wa_message_sequences references
        // wa_message_auto_responders, so it has to go before its parent.
        Schema::dropIfExists('wa_message_sequences');
        Schema::dropIfExists('wa_message_auto_responders');
        Schema::dropIfExists('wa_message_forward_campaigns');
    }

    private function migrateForwardCampaigns(): void
    {
        if (! Schema::hasTable('wa_message_forward_campaigns')) {
            return;
        }

        DB::table('wa_message_forward_campaigns')->orderBy('id')->chunkById(100, function ($campaigns) {
            foreach ($campaigns as $campaign) {
                $scheduledAt = $campaign->scheduled_at
                    ? \Illuminate\Support\Carbon::parse($campaign->scheduled_at)
                    : now();

                $rawRecipients = json_decode($campaign->recipients ?? '[]', true) ?: [];
                $recipients = collect($rawRecipients)->map(function (string $jid) {
                    if (str_ends_with($jid, '@g.us')) {
                        return ['type' => 'group', 'value' => $jid];
                    }

                    return ['type' => 'phone', 'value' => Str::before($jid, '@')];
                })->values()->all();

                DB::table('wa_message_schedules')->insert([
                    'id' => (string) Str::uuid(),
                    'company_id' => $campaign->company_id,
                    'device_id' => $campaign->device_id,
                    'title' => $campaign->title,
                    'type' => 'once',
                    // message_type was text|image|document — carried over
                    // as-is into category_schedule, which now accepts that
                    // same set (see MessageScheduleController's validator).
                    'category_schedule' => $campaign->message_type,
                    'message' => $campaign->message_content,
                    'use_template' => false,
                    'wa_message_template_id' => null,
                    'recipients' => json_encode($recipients),
                    'date_start' => $scheduledAt->toDateString(),
                    'date_end' => $scheduledAt->toDateString(),
                    'schedule_time' => $scheduledAt->format('H:i:s'),
                    'status' => $campaign->status,
                    'created_at' => $campaign->created_at,
                    'updated_at' => $campaign->updated_at,
                ]);
            }
        });
    }

    private function migrateAutoResponders(): void
    {
        if (! Schema::hasTable('wa_message_auto_responders')) {
            return;
        }

        DB::table('wa_message_auto_responders')->orderBy('id')->chunkById(100, function ($responders) {
            foreach ($responders as $responder) {
                $scheduleId = (string) Str::uuid();

                DB::table('wa_message_schedules')->insert([
                    'id' => $scheduleId,
                    'company_id' => $responder->company_id,
                    'device_id' => $responder->device_id,
                    'title' => 'Balasan Otomatis - '.$responder->phone_number,
                    'type' => 'drip',
                    // Parent carries no content for a drip schedule —
                    // each step brings its own (see below).
                    'category_schedule' => null,
                    'message' => null,
                    'use_template' => false,
                    'wa_message_template_id' => null,
                    'recipients' => json_encode([['type' => 'phone', 'value' => $responder->phone_number]]),
                    'date_start' => $responder->start_date,
                    'date_end' => $responder->start_date,
                    // The old drip engine had no time-of-day control (a
                    // step fired as soon as its due date started) —
                    // midnight preserves that as closely as the new,
                    // stricter schedule_time model allows.
                    'schedule_time' => '00:00:00',
                    'status' => $responder->status,
                    'created_at' => $responder->created_at,
                    'updated_at' => $responder->updated_at,
                ]);

                $steps = DB::table('wa_message_sequences')
                    ->where('message_auto_responder_id', $responder->id)
                    ->orderBy('sequence_order')
                    ->get();

                foreach ($steps as $step) {
                    DB::table('wa_message_schedule_steps')->insert([
                        'id' => (string) Str::uuid(),
                        'wa_message_schedule_id' => $scheduleId,
                        'sequence_order' => $step->sequence_order,
                        'delay_days' => $step->delay_days,
                        'use_template' => false,
                        'wa_message_template_id' => null,
                        // message_type was text|location|button|template —
                        // carried over as-is (same widened acceptance as
                        // Forward Campaign's message_type above).
                        'category_schedule' => $step->message_type,
                        'message' => $step->message_content,
                        'status' => $step->status,
                        'created_at' => $step->created_at,
                        'updated_at' => $step->updated_at,
                    ]);
                }
            }
        });
    }

    /**
     * Recreates the 3 tables' original structure so the schema is
     * reversible, but does NOT attempt to reconstruct their data — by
     * the time anyone rolls back this far, the unified
     * wa_message_schedules/steps rows are the live data and splitting
     * them back apart isn't a lossless operation (a drip schedule with
     * multi-recipient tabs, for instance, has no equivalent in the old
     * one-contact-per-enrollment shape). Restore from a backup taken
     * before this migration ran if the old rows themselves are needed.
     */
    public function down(): void
    {
        Schema::create('wa_message_forward_campaigns', function ($table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('device_id', 36);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('message_type', 20)->default('text');
            $table->text('message_content');
            $table->json('recipients');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('wa_message_auto_responders', function ($table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('device_id', 36);
            $table->string('phone_number', 32);
            $table->date('start_date');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('wa_message_sequences', function ($table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('message_auto_responder_id')->constrained('wa_message_auto_responders')->cascadeOnDelete();
            $table->string('device_id', 36);
            $table->unsignedInteger('sequence_order')->default(1);
            $table->unsignedInteger('delay_days')->default(0);
            $table->string('message_type', 20);
            $table->text('message_content');
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });
    }
};
