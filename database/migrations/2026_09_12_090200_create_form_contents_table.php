<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris pertanyaan/field dalam satu Form Header -- ini yang publik
 * isi di app.konexa.id/{slug}. `type` menentukan cara App\Http\
 * Controllers\Form\PublicFormController merender & memvalidasi input:
 * single_line, textarea, single_choice, multiple_choice, file_upload.
 *
 * PDF/JPG/JPEG/PNG dari spek awal BUKAN tipe pertanyaan tersendiri --
 * itu daftar ekstensi yang diizinkan KHUSUS untuk type=file_upload,
 * disimpan di `allowed_file_types` (CSV pendek, bukan tabel terpisah,
 * karena cuma dipakai untuk validasi `mimes:` saat submit).
 *
 * `options` (JSON array of string) dipakai untuk single_choice /
 * multiple_choice -- daftar pilihan jawaban, sama pola dengan
 * App\Models\WaChatbotFlowStep::$options.
 *
 * `position` + auto-increment saat create + orderBy('position') di
 * relasi -- PERSIS pola yang baru diperbaiki di App\Models\
 * WaChatbotFlow::steps() sesi ini (lihat App\Http\Controllers\Chat\
 * ChatbotFlowController::storeStep()), supaya urutan pertanyaan di form
 * builder selalu sesuai urutan dibuat, bukan acak.
 *
 * `is_required` -- field standar form builder yang tidak disebutkan di
 * spek tapi wajib ada supaya validasi submission bisa jalan; default
 * true (paling aman, admin bisa matikan per pertanyaan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_contents', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'form_cnt_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'form_cnt_branch_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('form_category_id')
                ->constrained(table: 'form_categories', indexName: 'form_cnt_category_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('form_header_id')
                ->constrained(table: 'form_headers', indexName: 'form_cnt_header_fk')
                ->cascadeOnDelete();

            $table->string('name', 255); // label pertanyaan
            $table->string('type', 30); // single_line | textarea | single_choice | multiple_choice | file_upload

            $table->json('options')->nullable(); // untuk single_choice / multiple_choice
            $table->string('allowed_file_types', 100)->nullable(); // csv, mis. "pdf,jpg,jpeg,png" -- untuk file_upload

            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('position')->default(0);

            $table->timestamps();

            $table->index(['form_header_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_contents');
    }
};
