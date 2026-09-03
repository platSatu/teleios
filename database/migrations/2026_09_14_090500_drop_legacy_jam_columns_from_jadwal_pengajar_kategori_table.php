<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration cleanup -- BUKAN duplikat dari edit di migration
 * `2026_09_14_090300_create_jadwal_pengajar_kategori_table.php`.
 *
 * Laravel mencatat migration yang sudah jalan lewat NAMA FILE-nya,
 * bukan isinya. Di server user, migration 090300 SUDAH SEMPAT jalan
 * lebih dulu (sebelum ketersediaan Pengajar direstruktur jadi banyak
 * slot hari/jam sesi ini) dengan kolom `hari_bisa`/`jam_mulai`/
 * `jam_selesai` versi lama (NOT NULL, tanpa default) langsung di tabel
 * `jadwal_pengajar_kategori`. Waktu file migration 090300 diedit untuk
 * menghapus kolom-kolom itu (karena ketersediaan sekarang pindah ke
 * tabel anak `jadwal_pengajar_kategori_jadwal`, lihat migration
 * `2026_09_14_090400_...`), `php artisan migrate` MELEWATI 090300
 * (dianggap sudah pernah jalan) -- jadi kolom lama itu TETAP ada di
 * database production, sementara App\Models\JadwalPengajarKategori
 * sudah tidak mengisinya lagi ⇒ error "Field 'hari_bisa' doesn't have
 * a default value" saat insert.
 *
 * Migration terpisah dengan nama file BARU ini yang benar-benar
 * dieksekusi untuk menghapus kolom lama itu dari tabel yang SUDAH
 * ADA. Dibungkus `Schema::hasColumn()` supaya aman dijalankan di DUA
 * skenario: (1) database production yang sudah telanjur punya kolom
 * lama ini (dihapus), (2) instalasi baru yang migration 090300-nya
 * sudah versi final tanpa kolom ini sejak awal (no-op, tidak ada yang
 * perlu dihapus).
 */
return new class extends Migration
{
    private const COLUMNS = ['hari_bisa', 'jam_mulai', 'jam_selesai'];

    public function up(): void
    {
        $existing = array_filter(self::COLUMNS, fn ($column) => Schema::hasColumn('jadwal_pengajar_kategori', $column));

        if (empty($existing)) {
            return;
        }

        Schema::table('jadwal_pengajar_kategori', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pengajar_kategori', function (Blueprint $table) {
            if (! Schema::hasColumn('jadwal_pengajar_kategori', 'hari_bisa')) {
                $table->json('hari_bisa')->nullable();
            }
            if (! Schema::hasColumn('jadwal_pengajar_kategori', 'jam_mulai')) {
                $table->time('jam_mulai')->nullable();
            }
            if (! Schema::hasColumn('jadwal_pengajar_kategori', 'jam_selesai')) {
                $table->time('jam_selesai')->nullable();
            }
        });
    }
};
