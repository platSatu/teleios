<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu Form Header = satu form publik yang bisa diisi orang lewat URL
 * app.konexa.id/{slug} (lihat App\Http\Controllers\Form\
 * PublicFormController -- rute TOP-LEVEL, di luar prefix 'dashboard' &
 * TANPA middleware auth, sama seperti /dokumentasi yang sudah ada).
 *
 * `slug` sengaja UNIK SECARA GLOBAL (bukan per company/branch) karena
 * URL-nya top-level, bukan app.konexa.id/{company}/{slug} -- lihat
 * App\Http\Controllers\Form\FormHeaderController::uniqueSlug() untuk
 * generator-nya (dari `name`, plus daftar kata terlarang supaya tidak
 * bentrok dengan rute sistem seperti "dashboard"/"login"/"storage").
 *
 * `background` menyimpan path relatif di bawah public/form (BUKAN
 * storage/app/public + symlink) -- ikut App\Helpers\JadwalImageUploader's
 * pola persis, cuma folder-nya "form" bukan "jadwal".
 *
 * `start_date`/`end_date` WAJIB diisi (bukan nullable) -- form Google-
 * Forms-style ini selalu punya jendela waktu aktif; di luar rentang itu
 * PublicFormController menolak submission meski status-nya 'active'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_headers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'form_hdr_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'form_hdr_branch_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('form_category_id')
                ->constrained(table: 'form_categories', indexName: 'form_hdr_category_fk')
                ->cascadeOnDelete();

            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->string('background')->nullable();
            $table->text('description')->nullable();

            $table->dateTime('start_date');
            $table->dateTime('end_date');

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            $table->index(['form_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_headers');
    }
};
