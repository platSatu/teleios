<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah pencatatan kehadiran ke App\Models\JadwalKelas. Sengaja
 * ditambahkan ke `jadwal_kelas` yang sudah ada (BUKAN tabel baru) --
 * satu baris jadwal_kelas MEMANG sudah = 1 pengajar + 1 student + 1
 * sesi (lihat migration create_jadwal_kelas_table.php's docblock),
 * jadi "hadir di sesi itu atau tidak" pas dicatat langsung di baris
 * yang sama. Tampilan "kelas grup" (banyak student per pengajar/mata
 * pelajaran) di index Jadwal Kelas cuma penggabungan sel ala Excel
 * (rowspan) atas baris-baris yang sudah ada -- bukan restrukturisasi
 * data.
 *
 * `attendance_status` sengaja terpisah dari `status` yang sudah ada
 * (yang artinya jadwal ini aktif/nonaktif, bukan soal kehadiran).
 * NULL = belum diabsen (default, sebelum sesinya berlangsung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            // 'hadir' | 'tidak_hadir' | NULL (belum diabsen)
            $table->string('attendance_status', 20)->nullable()->after('status');
            $table->text('attendance_notes')->nullable()->after('attendance_status');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropColumn(['attendance_status', 'attendance_notes']);
        });
    }
};
