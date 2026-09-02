<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalKategori — level BARU di bawah "Kelas"
 * (App\Models\JadwalMataPelajaran), mis. Kelas "Piano" punya Kategori
 * "Classic Level 1" (harga 400rb) & "Classic Level 2" (harga 500rb),
 * Kelas "Piano Pop" cukup 1 Kategori flat karena tidak ada level (spec
 * Jadwal v2 poin 3 di CLAUDE.md). Harga & persentase split company/
 * pengajar SENGAJA per-Kategori (bukan setting global 1 angka) karena
 * confirmed berbeda-beda per kategori.
 *
 * `persentase_company` + `persentase_pengajar` divalidasi harus = 100
 * di level app (App\Http\Controllers\Jadwal\JadwalKategoriController),
 * bukan DB check constraint, supaya pesan error bisa dalam Bahasa
 * Indonesia yang jelas dan konsisten dengan pola validasi form lain di
 * app ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_kategori', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('jadwal_mata_pelajaran_id')
                ->constrained('jadwal_mata_pelajaran')
                ->cascadeOnDelete();

            $table->string('name');

            // Harga per satu sesi pertemuan, milik Kategori ini.
            $table->decimal('harga_per_sesi', 12, 2);

            // Persentase split fee dari harga_per_sesi di atas --
            // company + pengajar harus berjumlah 100.
            $table->decimal('persentase_company', 5, 2);
            $table->decimal('persentase_pengajar', 5, 2);

            // 'active' | 'inactive'
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['company_id', 'jadwal_mata_pelajaran_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_kategori');
    }
};
