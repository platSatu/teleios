<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 2 integrasi Chat<->Jadwal -- satu baris pengaturan pengingat WA
 * PER COMPANY (unique company_id, bukan tabel banyak-baris). Diakses
 * lewat menu "Pengaturan Pengingat" di bawah Jadwal (lihat
 * App\Http\Controllers\Jadwal\JadwalReminderSettingController) yang
 * cuma tampil/berfungsi kalau company punya package aktif kategori
 * Chat/WhatsApp -- lihat App\Services\PackageLimitService::
 * hasActiveCategoryPackage() & App\Models\JadwalReminderSetting::
 * CHAT_CATEGORY_NAMES.
 *
 * `device_id` sengaja plain string (bukan foreign key) -- sama seperti
 * wa_message_schedules.device_id, karena wa_devices dimiliki/dibuat Go
 * backend (GORM AutoMigrate), Laravel tidak punya Eloquent model untuk
 * tabel itu (lihat App\Services\Chat\DeviceDirectory's docblock).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_reminder_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->unique()
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->boolean('enabled')->default(false);

            $table->string('device_id', 36)->nullable();

            $table->foreignUuid('wa_message_template_id')
                ->nullable()
                ->constrained('wa_message_templates')
                ->nullOnDelete();

            // Berapa lama sebelum start_time pengingat dikirim -- angka +
            // satuan, bisa diatur per company (lihat diskusi: tempat
            // kursus berbeda punya kebutuhan berbeda, tidak di-hardcode
            // H-1 untuk semua).
            $table->unsignedSmallInteger('remind_value')->default(1);

            // 'hours' | 'days'
            $table->string('remind_unit', 10)->default('days');

            // 'parent' | 'student' | 'both' -- lihat
            // jadwal_student.parent_phone_number/student_phone_number.
            $table->string('remind_target', 10)->default('parent');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_reminder_settings');
    }
};
