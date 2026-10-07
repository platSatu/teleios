<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan Student -> Pelanggan Tagihan (7 Oktober 2026, tombol "Daftarkan
 * Tagihan" di index Student, lihat App\Services\Jadwal\StudentTagihanLink).
 * Satu murid = satu TagihanPelanggan miliknya sendiri, supaya klik ulang
 * tidak membuat pelanggan dobel. Nullable: murid yang belum didaftarkan
 * tetap kosong. Kalau pelanggannya dihapus dari menu Tagihan, kolom ini
 * jadi NULL (Student tidak ikut terhapus).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_student', function (Blueprint $table) {
            $table->foreignUuid('tagihan_pelanggan_id')->nullable()->after('form_id')
                ->constrained('tagihan_pelanggan')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_student', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tagihan_pelanggan_id');
        });
    }
};
