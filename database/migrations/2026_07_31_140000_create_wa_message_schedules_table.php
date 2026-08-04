<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaMessageSchedule — a company's scheduled
     * WhatsApp messages ("Pesan terjadwal" in the sidebar). device_id is
     * a plain string, NOT a foreignUuid: WhatsApp devices live entirely
     * in the separate Go backend's own database (see
     * App\Services\Chat\ConnectDeviceService) — there is no local
     * `wa_devices` table in this Laravel app to reference.
     *
     * Fields beyond the original spec (title, message, is_group,
     * group_jid, phone_number) were added because a "schedule" needs
     * both a way to identify itself in a list and something to actually
     * send/somewhere to send it to — the original spec only carried
     * category/date/time/status.
     */
    public function up(): void
    {
        Schema::create('wa_message_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('device_id', 36);

            $table->string('title');

            // text | location
            $table->string('category_schedule', 20);

            // The message body for category_schedule = text, or a
            // "lat,lng" / free-form address for category_schedule =
            // location.
            $table->text('message')->nullable();

            // Toggled ("di-slide") on the form to reveal group_jid
            // instead of phone_number as the target.
            $table->boolean('is_group')->default(false);
            $table->string('group_jid')->nullable();
            $table->string('phone_number', 32)->nullable();

            $table->date('schedule_date');
            $table->time('schedule_time');

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_schedules');
    }
};
