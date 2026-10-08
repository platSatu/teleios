<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Harga & fee pengajar sepenuhnya di Grade (8 Oktober 2026): form Kategori
 * tidak lagi mengisi harga_bulanan/persentase_company/persentase_pengajar.
 * Kolomnya TIDAK dihapus (data lama tetap ada sebagai riwayat), cukup
 * dijadikan nullable supaya Kategori baru bisa disimpan tanpa harga.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE jadwal_kategori
            MODIFY harga_bulanan DECIMAL(12,2) NULL,
            MODIFY persentase_company DECIMAL(5,2) NULL,
            MODIFY persentase_pengajar DECIMAL(5,2) NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE jadwal_kategori SET harga_bulanan = COALESCE(harga_bulanan, 0),
            persentase_company = COALESCE(persentase_company, 0),
            persentase_pengajar = COALESCE(persentase_pengajar, 0)');

        DB::statement('ALTER TABLE jadwal_kategori
            MODIFY harga_bulanan DECIMAL(12,2) NOT NULL,
            MODIFY persentase_company DECIMAL(5,2) NOT NULL,
            MODIFY persentase_pengajar DECIMAL(5,2) NOT NULL');
    }
};
