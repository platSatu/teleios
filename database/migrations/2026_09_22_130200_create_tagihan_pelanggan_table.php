<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontak yang ditagih lewat fitur Tagihan -- BERDIRI SENDIRI, sengaja
 * TIDAK di-link ke App\Models\JadwalStudent/App\Models\WaCustomer
 * (keputusan user 22 September 2026: "sementara dibuat terpisah dulu
 * aja karena kita belum sentuh aplikasi yang lainnya" -- ini murni
 * aplikasi pembayaran, tanpa sangkut paut ke modul lain untuk saat
 * ini). Kalau nanti mau dihubungkan ke Jadwal/Chat, itu perubahan
 * terpisah -- jangan asumsikan relasi itu ada dari sini.
 *
 * Branch-scoped, pola yang sama dengan tagihan_category/form_categories
 * -- satu pelanggan selalu tercatat di SATU cabang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_pelanggan', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'tagihan_plg_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'tagihan_plg_branch_fk')
                ->cascadeOnDelete();

            $table->string('name', 255);

            // Nullable -- belum tentu semua pelanggan punya nomor WA
            // saat dibuat, dan kolom ini memang belum dipakai kirim apa
            // pun sekarang (lihat App\Models\TagihanReminderRule's
            // docblock: reminder belum disambungkan ke WhatsApp).
            $table->string('phone_number', 30)->nullable();
            $table->string('email')->nullable();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            $table->index(['company_id', 'branch_office_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_pelanggan');
    }
};
