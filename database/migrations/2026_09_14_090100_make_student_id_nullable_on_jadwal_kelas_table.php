<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `jadwal_kelas.student_id` jadi NULLABLE -- mendukung "slot kosong"
 * (pengajar + jam + ruangan sudah dibuat manual lewat
 * JadwalKelasController, tapi murid belum ditentukan). Perilaku
 * `jadwal:generate-sesi` (App\Console\Commands\GenerateJadwalRutinSesi)
 * TIDAK berubah -- sesi hasil generate selalu punya student_id
 * karena di-copy dari JadwalRutin, yang student_id-nya tetap WAJIB
 * (di luar scope perubahan ini, lihat CLAUDE.md item #15).
 *
 * Tidak pakai Blueprint::change() (butuh doctrine/dbal yang tidak
 * terpasang di project ini, lihat composer.lock) -- pola drop-FK /
 * ALTER MODIFY / re-add-FK ini ikut migration korektif
 * 2026_09_01_090000_fix_jadwal_kelas_student_id_foreign_key.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropForeign('jadwal_kelas_student_id_foreign');
        });

        DB::statement('ALTER TABLE jadwal_kelas MODIFY student_id CHAR(36) NULL');

        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->foreign('student_id', 'jadwal_kelas_student_id_foreign')
                ->references('id')->on('jadwal_student')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Kalau ada baris student_id NULL yang masih tersisa, isi
        // dulu / hapus manual sebelum rollback -- MODIFY NOT NULL akan
        // gagal kalau masih ada NULL di kolom ini.
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropForeign('jadwal_kelas_student_id_foreign');
        });

        DB::statement('ALTER TABLE jadwal_kelas MODIFY student_id CHAR(36) NOT NULL');

        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->foreign('student_id', 'jadwal_kelas_student_id_foreign')
                ->references('id')->on('jadwal_student')
                ->restrictOnDelete();
        });
    }
};
