<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur Form -- Google-Forms-style form builder per branch. Ini level
 * paling atas dari rangkaian: Branch -> Form Category -> Form Header ->
 * Form Content -> Form Footer -> Form Setting (drill-down yang sama
 * persis dengan pola "ina"/Jadwal -- lihat App\Http\Controllers\
 * Jadwal\JadwalBranchController's docblock).
 *
 * `branch_office_id` WAJIB (bukan nullable seperti Jadwal Mata Pelajaran)
 * -- sesuai spek "tiap branch bisa create form", satu Form Category
 * selalu milik SATU branch tertentu, tidak ada konsep "company-wide,
 * semua branch pakai".
 *
 * `company_id` didenormalisasi di sini (dan di semua tabel form_*
 * turunannya) supaya query scoped-ke-company tidak perlu join balik ke
 * branch_offices tiap kali -- pola yang sama dipakai
 * jadwal_mata_pelajarans dkk.
 *
 * Tombol "Copy" di index (deep-clone seluruh rangkaian: category ->
 * semua header -> semua content/footer/setting-nya) murni logic
 * controller, tidak butuh kolom tambahan di sini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'form_cat_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'form_cat_branch_fk')
                ->cascadeOnDelete();

            $table->string('name', 255);
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            $table->index(['company_id', 'branch_office_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_categories');
    }
};
