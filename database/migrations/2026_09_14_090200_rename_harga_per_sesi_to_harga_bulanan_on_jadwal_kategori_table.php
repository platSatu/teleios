<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ganti model input harga Kategori: dari "harga per sesi" langsung jadi
 * "harga bulanan" (dibagi jumlah sesi/bulan branch, App\Models\
 * JadwalBranchSetting::sesi_per_bulan_default, saat snapshot ke
 * JadwalKelas -- lihat App\Models\JadwalKategori::hargaPerSesi()).
 * Kolom cuma di-rename (tetap decimal(12,2), NILAI YANG SUDAH ADA
 * dianggap sebagai harga bulanan sekarang, bukan dikonversi) --
 * data lama (kalau ada) perlu dicek/diisi ulang manual oleh admin,
 * tidak ada auto-migrate nilai di sini karena tidak ada cara aman
 * menebak jumlah sesi/bulan yang dipakai historis per baris.
 *
 * Tidak pakai Blueprint::renameColumn() (butuh doctrine/dbal yang
 * tidak terpasang, lihat composer.lock -- sama seperti migration
 * 2026_09_14_090100).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE jadwal_kategori CHANGE harga_per_sesi harga_bulanan DECIMAL(12,2) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jadwal_kategori CHANGE harga_bulanan harga_per_sesi DECIMAL(12,2) NOT NULL');
    }
};
