<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagian dari Jadwal v2 (lihat CLAUDE.md item #15) -- menghubungkan
 * App\Models\JadwalKelas ("Sesi") ke sumber jadwal rutinnya, dan
 * menambahkan kolom SNAPSHOT (jadwal_kategori_id, jadwal_ruangan_id,
 * duration_minutes, harga_sesi, persentase_company, persentase_pengajar)
 * yang di-copy dari App\Models\JadwalRutin/JadwalKategori PADA SAAT
 * generate -- sengaja di-snapshot (bukan selalu dibaca live dari
 * Kategori) supaya laporan fee historis tetap akurat walau harga/
 * persentase Kategori diubah admin di kemudian hari.
 *
 * `pengganti_dari_sesi_id` (self-referencing, nullable) menandai baris
 * ini sebagai SESI PENGGANTI untuk sesi lain yang izin/sakit -- baris
 * BARU, bukan mengubah baris asli, supaya histori "izin tanggal X,
 * pengganti tanggal Y" tetap tercatat utuh untuk laporan (spec poin 8).
 *
 * Semua kolom baru nullable -- baris jadwal_kelas lama (dibuat manual
 * sebelum Jadwal v2 ada) tetap valid tanpa nilai-nilai ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->foreignUuid('jadwal_rutin_id')
                ->nullable()
                ->after('student_id')
                ->constrained('jadwal_rutin')
                ->nullOnDelete();

            $table->foreignUuid('jadwal_kategori_id')
                ->nullable()
                ->after('jadwal_rutin_id')
                ->constrained('jadwal_kategori')
                ->nullOnDelete();

            $table->foreignUuid('jadwal_ruangan_id')
                ->nullable()
                ->after('jadwal_kategori_id')
                ->constrained('jadwal_ruangan')
                ->nullOnDelete();

            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('end_time');

            // Snapshot dari jadwal_kategori.harga_per_sesi /
            // persentase_company / persentase_pengajar pada saat sesi
            // ini digenerate -- lihat docblock file ini.
            $table->decimal('harga_sesi', 12, 2)->nullable()->after('duration_minutes');
            $table->decimal('persentase_company', 5, 2)->nullable()->after('harga_sesi');
            $table->decimal('persentase_pengajar', 5, 2)->nullable()->after('persentase_company');

            // Self-reference -- lihat docblock file ini.
            $table->foreignUuid('pengganti_dari_sesi_id')
                ->nullable()
                ->after('persentase_pengajar')
                ->constrained('jadwal_kelas')
                ->nullOnDelete();

            $table->index(['jadwal_rutin_id']);
            $table->index(['pengganti_dari_sesi_id']);
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pengganti_dari_sesi_id');
            $table->dropConstrainedForeignId('jadwal_ruangan_id');
            $table->dropConstrainedForeignId('jadwal_kategori_id');
            $table->dropConstrainedForeignId('jadwal_rutin_id');

            $table->dropColumn([
                'duration_minutes',
                'harga_sesi',
                'persentase_company',
                'persentase_pengajar',
            ]);
        });
    }
};
