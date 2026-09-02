<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagian dari Jadwal v2 (CLAUDE.md item #15, spec poin 9 & 10) --
 * menambahkan pengaturan reminder WA KE PENGAJAR ke baris
 * jadwal_reminder_settings yang sudah ada (satu baris per company),
 * SENGAJA menumpang tabel yang sama seperti reschedule_notify_* (lihat
 * migration add_reschedule_notification_settings_..._table.php) supaya
 * device pengirim & pengaturan tetap satu sumber, tidak hardcode
 * terpisah -- device_id yang sudah ada dipakai ulang, tidak perlu
 * kolom device baru.
 *
 * `wa_message_template_id_pengajar` template TERPISAH dari
 * `wa_message_template_id` (yang isinya untuk parent/student) karena
 * placeholder-nya beda -- rekap BANYAK sesi besok (jam, murid) per
 * pengajar, bukan 1 sesi per murid.
 *
 * `pengajar_request_keyword` -- kata kunci WA yang dibalas otomatis
 * dengan rekap jadwal minggu ini milik pengirim (dicocokkan ke
 * users.handphone), dipakai App\Services\Chat\ChatbotFlowService atau
 * listener pesan masuk yang relevan. Default 'jadwal' tapi tetap bisa
 * diubah admin -- tidak hardcode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_reminder_settings', function (Blueprint $table) {
            $table->boolean('remind_notify_pengajar')->default(false)->after('remind_target');

            $table->foreignUuid('wa_message_template_id_pengajar')
                ->nullable()
                ->after('wa_message_template_id')
                ->constrained('wa_message_templates')
                ->nullOnDelete();

            $table->string('pengajar_request_keyword', 50)->default('jadwal')->after('wa_message_template_id_pengajar');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_reminder_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wa_message_template_id_pengajar');
            $table->dropColumn(['remind_notify_pengajar', 'pengajar_request_keyword']);
        });
    }
};
