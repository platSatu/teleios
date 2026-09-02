<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1 baris per Form Header (unique di form_header_id) -- pengaturan apa
 * yang terjadi begitu submission publik berhasil disimpan, dieksekusi
 * dari App\Http\Controllers\Form\PublicFormController::store().
 *
 * `notify_wa_enabled` + `wa_message_template_id` -- notifikasi WA ke
 * pengisi form saat submit berhasil, TAPI cuma benar-benar terkirim
 * kalau company punya paket kategori "Whatsapp Blast" aktif (dicek
 * lewat App\Services\PackageLimitService::hasActiveCategoryPackage(),
 * sama persis seperti App\Models\JadwalReminderSetting::
 * CHAT_CATEGORY_NAMES) -- kalau tidak subscribe, checkbox ini boleh
 * aktif di DB tapi pengiriman di-skip diam-diam, bukan error.
 *
 * `additional_info` -- textarea bebas dari spek ("link zoom, atau
 * lainnya"), ikut dikirim di pesan WA konfirmasi kalau notify aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'form_set_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'form_set_branch_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('form_category_id')
                ->constrained(table: 'form_categories', indexName: 'form_set_category_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('form_header_id')
                ->unique()
                ->constrained(table: 'form_headers', indexName: 'form_set_header_fk')
                ->cascadeOnDelete();

            // Plain string, bukan foreign key -- sama seperti
            // jadwal_reminder_settings.device_id, karena wa_devices
            // dimiliki/dibuat Go backend (g_backend), Laravel tidak
            // pegang tabelnya sendiri.
            $table->string('device_id', 36)->nullable();

            $table->boolean('notify_wa_enabled')->default(false);

            $table->foreignUuid('wa_message_template_id')
                ->nullable()
                ->constrained(table: 'wa_message_templates', indexName: 'form_set_wa_tpl_fk')
                ->nullOnDelete();

            $table->text('additional_info')->nullable(); // mis. link zoom

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_settings');
    }
};
