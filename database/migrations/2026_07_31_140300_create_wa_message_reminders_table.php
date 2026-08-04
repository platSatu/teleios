<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\WaMessageReminder ("Pengingat") — a one-off
     * reminder message sent at start_reminder, to either a phone_number
     * or a WhatsApp group (is_group toggle, same "slide to reveal a
     * group picker" behaviour as wa_message_schedules.is_group).
     */
    public function up(): void
    {
        Schema::create('wa_message_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('device_id', 36);

            // Free-form category label (e.g. "Pembayaran", "Follow Up",
            // "Umum") — kept as a plain string rather than a fixed enum
            // since the useful set of categories is a business decision,
            // not a technical one.
            $table->string('category_message_reminder', 50);

            $table->string('title_reminder');
            $table->text('message');
            $table->dateTime('start_reminder');

            $table->boolean('is_group')->default(false);
            $table->string('group_jid')->nullable();
            $table->string('phone_number', 32)->nullable();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_message_reminders');
    }
};
