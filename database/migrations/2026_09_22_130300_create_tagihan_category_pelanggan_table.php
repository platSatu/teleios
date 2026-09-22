<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Langganan" App\Models\TagihanPelanggan ke App\Models\TagihanCategory
 * -- checklist yang dimaksud user 22 September 2026 ("dalam proses
 * assign itu, nanti akan ada ceklist user itu dimasukan ke pembayaran
 * mana aja, artinya secara otomatis juga akan berulang").
 *
 * Ini BUKAN yang membuat App\Models\Tagihan baru secara otomatis --
 * admin tetap yang membuat tiap baris Tagihan per periode secara
 * manual. Baris di sini cuma DAFTAR CALON PENERIMA yang dipakai buat
 * mengisi otomatis App\Models\TagihanPenerima begitu admin membuat
 * Tagihan baru di bawah category ini -- admin masih bisa
 * menambah/mengurangi penerima khusus untuk satu Tagihan tertentu
 * tanpa mengubah baris langganan di sini.
 *
 * `nominal_override` -- nullable, isi kalau pelanggan ini py nominal
 * beda dari default_amount category (misal ada potongan/beasiswa).
 * NULL berarti ikut default_amount category / amount Tagihan-nya.
 *
 * `status` (bukan hard delete) -- kalau pelanggan berhenti langganan,
 * baris ini di-nonaktifkan (status='inactive'), BUKAN dihapus, supaya
 * Tagihan/TagihanPenerima yang sudah pernah dibuat sebelumnya (histori)
 * tetap utuh datanya (foreign key tetap valid, tidak org-phan) --
 * konfirmasi user: "tagihan yang sudah terlanjur dibuat sebelumnya
 * tetap ada datanya kan, cuma mulai bulan depan tidak dibuatkan lagi".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_category_pelanggan', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tagihan_category_id')
                ->constrained(table: 'tagihan_category', indexName: 'tagihan_cat_plg_category_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('tagihan_pelanggan_id')
                ->constrained(table: 'tagihan_pelanggan', indexName: 'tagihan_cat_plg_pelanggan_fk')
                ->cascadeOnDelete();

            $table->decimal('nominal_override', 15, 2)->nullable();

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            $table->unique(
                ['tagihan_category_id', 'tagihan_pelanggan_id'],
                'tagihan_cat_plg_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_category_pelanggan');
    }
};
