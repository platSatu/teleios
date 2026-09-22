<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan denda keterlambatan BERTINGKAT per App\Models\TagihanCategory
 * (diskusi 22 September 2026, contoh dari user: "uang sekolah due date
 * tanggal 15, belum dibayar sampai tanggal 20 dendanya 0.5% per hari,
 * di atas tanggal 20 jadi flat 1000 rupiah").
 *
 * Satu tier = satu rentang hari SEJAK due_date (offset, bukan tanggal
 * kalender -- due_date beda tiap Tagihan, jadi tier-nya relatif):
 *   - `mulai_hari_ke` 0 = hari H due_date itu sendiri.
 *   - `sampai_hari_ke` NULL = berlaku seterusnya (tier terakhir/paling
 *     jauh) -- HARUS ada tepat satu tier ber-`sampai_hari_ke` NULL per
 *     category, divalidasi di controller.
 * Dihitung sequential dari `urutan` terkecil ke terbesar; App\Models\
 * TagihanPenerima::hitungDenda() (menyusul di fase controller/service)
 * loop tier-tier ini sesuai berapa hari sebuah tagihan sudah lewat
 * jatuh tempo.
 *
 * Contoh user di atas jadi 2 baris:
 *   urutan=1, mulai_hari_ke=0,  sampai_hari_ke=5,    tipe=persen_per_hari, nilai=0.5
 *   urutan=2, mulai_hari_ke=6,  sampai_hari_ke=NULL, tipe=flat,            nilai=1000, frekuensi_flat=?
 *
 * `frekuensi_flat` ('per_hari' | 'sekali') cuma dipakai kalau
 * tipe='flat' -- apakah nominal flat itu nambah tiap hari telat, atau
 * cuma dikenakan sekali berapa pun lama telatnya (permintaan user:
 * "itu bisa disettingan artinya kolom nya di sediakan").
 *
 * `nilai` pakai presisi decimal(15,4) (bukan 15,2 seperti kolom uang
 * lain di project ini) supaya persentase kecil semacam 0.5% tidak
 * kepotong pembulatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_denda_tier', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tagihan_category_id')
                ->constrained(table: 'tagihan_category', indexName: 'tagihan_denda_tier_category_fk')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('urutan')->default(1);

            $table->unsignedSmallInteger('mulai_hari_ke');
            $table->unsignedSmallInteger('sampai_hari_ke')->nullable();

            // 'persen_per_hari' | 'flat'
            $table->string('tipe', 20);

            $table->decimal('nilai', 15, 4);

            // 'per_hari' | 'sekali' -- nullable, cuma relevan kalau
            // tipe='flat'. NULL kalau tipe='persen_per_hari' (persen
            // selalu dihitung per hari, tidak ada mode "sekali").
            $table->string('frekuensi_flat', 10)->nullable();

            $table->timestamps();

            $table->index(['tagihan_category_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_denda_tier');
    }
};
