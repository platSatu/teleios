<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu Tagihan = satu periode/invoice di bawah App\Models\TagihanCategory
 * (contoh user 22 September 2026: "uang sekolah januari 2026, uang
 * sekolah februari 2026, uang buku 2026" -- masing-masing baris di sini
 * dibuat MANUAL oleh admin, bukan digenerate otomatis oleh job
 * terjadwal; lihat App\Models\TagihanCategory's docblock).
 *
 * `amount`/`pakai_denda` adalah SALINAN dari default category saat
 * Tagihan ini dibuat (bukan foreign key ke category yang dibaca ulang
 * tiap saat) -- supaya kalau default category-nya diedit belakangan,
 * Tagihan yang sudah pernah dibuat tidak ikut berubah nominal/aturan
 * dendanya secara diam-diam. App\Models\TagihanPenerima nanti salin
 * lagi amount-nya sendiri dari sini (bisa di-override lagi per orang),
 * jadi ada 3 lapis: default category -> default Tagihan -> nominal
 * final per TagihanPenerima, masing-masing independen begitu ditulis.
 *
 * `due_date` WAJIB diisi (bukan nullable) -- konfirmasi eksplisit user:
 * "kalau due date harus diisi". `pakai_denda` defaultnya ikut
 * `denda_enabled` category saat dibuat, tapi bisa dimatikan khusus
 * untuk Tagihan ini saja (nullable secara konsep -- "denda bisa
 * dipasang atau tidak" -- diwujudkan sebagai boolean toggle, bukan
 * duplikasi seluruh aturan tier per Tagihan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'tagihan_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'tagihan_branch_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('tagihan_category_id')
                ->constrained(table: 'tagihan_category', indexName: 'tagihan_category_fk')
                ->restrictOnDelete();

            $table->string('name', 255);

            $table->decimal('amount', 15, 2);

            $table->date('due_date');

            $table->boolean('pakai_denda')->default(false);

            $table->string('status', 20)->default('active'); // active | dibatalkan

            $table->timestamps();

            $table->index(['tagihan_category_id']);
            $table->index(['company_id', 'branch_office_id']);
            $table->index(['due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};
