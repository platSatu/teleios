<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Blok penutup form (teks terima kasih / catatan akhir / CTA setelah
 * pertanyaan terakhir) -- satu Form Header bisa punya beberapa baris
 * footer (mis. multi-bahasa atau beberapa CTA), sama seperti setiap
 * level lain di rangkaian form_* ini sengaja dibuat 1-banyak.
 *
 * SENGAJA tidak ada `form_content_id` di sini walau disebutkan di spek
 * awal -- footer itu penutup untuk KESELURUHAN form, bukan lampiran ke
 * satu pertanyaan tertentu, jadi relasinya cukup ke form_header_id saja
 * (didampingi form_category_id/branch_office_id/company_id untuk query
 * scoped langsung, sama seperti tabel form_* lain).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_footers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'form_ftr_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'form_ftr_branch_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('form_category_id')
                ->constrained(table: 'form_categories', indexName: 'form_ftr_category_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('form_header_id')
                ->constrained(table: 'form_headers', indexName: 'form_ftr_header_fk')
                ->cascadeOnDelete();

            $table->string('name', 255); // isi teks footer
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            $table->index(['form_header_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_footers');
    }
};
