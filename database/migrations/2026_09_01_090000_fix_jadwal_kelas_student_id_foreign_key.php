<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration korektif. `student_id` di migration
 * create_jadwal_kelas_table.php sudah diedit LANGSUNG (bukan lewat
 * migration baru) supaya FK-nya ke `jadwal_student` (bukan lagi
 * `users`) -- lihat file itu & create_jadwal_student_table.php's
 * docblock. Itu cuma berlaku untuk instalasi yang migrate dari nol.
 *
 * Server yang migration create_jadwal_kelas_table.php-nya SUDAH pernah
 * jalan (sebelum modul Jadwal dirombak jadi drill-down 5 level) masih
 * punya constraint LAMA ke `users` di database-nya -- Laravel tidak
 * menjalankan ulang migration yang sudah tercatat di tabel
 * `migrations`, walau isi filenya berubah. Akibatnya insert ke
 * jadwal_kelas dengan student_id dari jadwal_student (bukan users)
 * gagal kena foreign key constraint (SQLSTATE[23000] 1452). Migration
 * ini membetulkan constraint itu di server yang sudah terlanjur
 * migrate, supaya sama dengan skema yang seharusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kalau constraint lama (ke `users`) memang masih ada, lepas
        // dulu -- dibungkus try/catch supaya migration ini tetap aman
        // dijalankan di instalasi BARU yang dari awal sudah langsung FK
        // ke jadwal_student (constraint lama itu tidak akan pernah ada).
        try {
            Schema::table('jadwal_kelas', function (Blueprint $table) {
                $table->dropForeign('jadwal_kelas_student_id_foreign');
            });
        } catch (\Throwable $e) {
            // Constraint lama tidak ada (instalasi baru) -- lanjut saja.
        }

        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->foreign('student_id', 'jadwal_kelas_student_id_foreign')
                ->references('id')->on('jadwal_student')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropForeign('jadwal_kelas_student_id_foreign');
        });

        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->foreign('student_id', 'jadwal_kelas_student_id_foreign')
                ->references('id')->on('users')
                ->restrictOnDelete();
        });
    }
};
