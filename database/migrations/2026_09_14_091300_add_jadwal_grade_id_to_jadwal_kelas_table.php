<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan jadwal_grade_id (nullable, snapshot) ke jadwal_kelas --
 * pasangan migration add_jadwal_grade_id_to_jadwal_rutin_table.php,
 * lihat docblock-nya untuk alasan lengkap. jadwal_kategori_id yang
 * sudah ada TETAP dipertahankan (masih diisi apa adanya oleh
 * App\Services\Jadwal\JadwalRutinSesiGenerator & popup Edit Jadwal
 * Kelas), jadwal_grade_id ini murni tambahan supaya sesi bertanggal
 * juga tahu persis Grade mana yang harganya dipakai saat sesi itu
 * dibuat (histori fee tetap akurat walau Grade-nya diubah admin nanti).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->foreignUuid('jadwal_grade_id')
                ->nullable()
                ->after('jadwal_kategori_id')
                ->constrained('jadwal_grade')
                ->nullOnDelete();

            $table->index(['jadwal_grade_id']);
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('jadwal_grade_id');
        });
    }
};
