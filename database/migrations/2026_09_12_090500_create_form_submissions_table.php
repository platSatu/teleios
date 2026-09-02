<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUKAN bagian dari spek awal -- ditambahkan supaya form-nya benar-benar
 * berfungsi: form_categories..form_settings di atas semuanya cuma
 * mendefinisikan STRUKTUR form (builder-nya), tabel ini yang menampung
 * hasil isian tiap kali orang submit form publik di app.konexa.id/{slug}
 * (lihat App\Http\Controllers\Form\PublicFormController::store()).
 * Jawaban per pertanyaan ada di form_submission_answers (tabel
 * berikutnya), 1-banyak dari sini.
 *
 * `ip_address`/`user_agent` sekadar audit trail dasar (siapa yang
 * submit) -- pola yang sama seperti App\Models\FrontendVisitorLog,
 * bukan dipakai untuk identifikasi/rate-limit (itu urusan middleware
 * throttle di route-nya, bukan kolom di sini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'form_sub_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'form_sub_branch_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('form_category_id')
                ->constrained(table: 'form_categories', indexName: 'form_sub_category_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('form_header_id')
                ->constrained(table: 'form_headers', indexName: 'form_sub_header_fk')
                ->cascadeOnDelete();

            $table->string('ip_address', 45)->nullable(); // cukup untuk IPv6
            $table->text('user_agent')->nullable();

            $table->timestamp('submitted_at')->useCurrent();

            $table->timestamps();

            $table->index(['form_header_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
