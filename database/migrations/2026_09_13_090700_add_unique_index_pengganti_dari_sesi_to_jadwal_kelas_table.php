<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu sesi asli (izin/sakit) cuma boleh punya SATU sesi pengganti --
 * unique index di pengganti_dari_sesi_id (nullable-safe, banyak baris
 * NULL tetap bebas bentrok) mencegah 2 baris pengganti tercipta untuk
 * sesi asli yang sama walau lewat request bersamaan. Lihat
 * App\Http\Controllers\Jadwal\JadwalKelasController::store()'s
 * penanganan pengganti_dari_sesi_id (Jadwal v2, CLAUDE.md item #15
 * spec poin 8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->unique('pengganti_dari_sesi_id', 'jadwal_kelas_pengganti_dari_sesi_unique');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropUnique('jadwal_kelas_pengganti_dari_sesi_unique');
        });
    }
};
