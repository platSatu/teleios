<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunci idempotency generator sesi bulanan (App\Console\Commands\
 * GenerateJadwalRutinSesi, Jadwal v2 CLAUDE.md item #15 spec poin 6) --
 * unique index (jadwal_rutin_id, start_time) supaya generator yang
 * dijalankan ulang (retry, atau kebetulan overlap run) TIDAK pernah
 * membuat baris jadwal_kelas duplikat untuk tanggal yang sama, tanpa
 * perlu SELECT-then-INSERT yang rawan race condition -- generator
 * cukup coba INSERT dan tangkap QueryException kalau baris itu ternyata
 * sudah ada (pola sama seperti App\Console\Commands\
 * DispatchDueWaMessageSchedules::claimAndDispatch()).
 *
 * AMAN untuk baris jadwal_kelas LAMA/manual (jadwal_rutin_id NULL) --
 * MySQL menganggap tiap NULL berbeda satu sama lain dalam unique index,
 * jadi banyak baris manual dengan jadwal_rutin_id NULL tetap bebas
 * bentrok start_time berapa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->unique(['jadwal_rutin_id', 'start_time'], 'jadwal_kelas_rutin_start_unique');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropUnique('jadwal_kelas_rutin_start_unique');
        });
    }
};
