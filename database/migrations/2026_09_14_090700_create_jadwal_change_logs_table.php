<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalChangeLog — histori "jadwal SEBELUM diganti"
 * dan "jadwal SESUDAH diganti" (permintaan user: "sebaiknya di pindah
 * ke table baru jadwal sblm di ganti dan jadwal sesudah diganti"),
 * ditulis oleh App\Services\Jadwal\JadwalScheduleChangeNotifier dari DUA
 * jalur perubahan jadwal yang ada di app ini:
 *
 * 1. `source` = 'student_form' — Tambah/Edit Student (checklist
 *    ketersediaan Pengajar, App\Http\Controllers\Jadwal\
 *    JadwalStudentController). Uncheck slot lama + centang slot baru =
 *    DUA baris log terpisah di sini (bukan satu baris before+after
 *    berpasangan) — satu baris `before`-only waktu App\Models\
 *    JadwalRutin lama dihapus, satu baris `after`-only waktu JadwalRutin
 *    baru dibuat. Dipisah karena satu submit form checklist bisa
 *    mengubah BEBERAPA slot sekaligus (beberapa Kategori/hari), jadi
 *    tidak ada cara aman memasangkan "slot lama X jadi slot baru mana"
 *    kalau lebih dari satu slot berubah — dua baris independen tetap
 *    memberi jejak lengkap ("apa yang hilang" + "apa yang muncul") tanpa
 *    menebak pasangannya.
 * 2. `source` = 'jadwal_kelas_edit' — edit langsung SATU baris
 *    App\Models\JadwalKelas lewat popup Jadwal Kelas
 *    (JadwalKelasController::update()). Di sini SATU baris log sudah
 *    cukup (before+after di baris yang sama) karena yang berubah
 *    memang satu sesi yang sama, bukan sekumpulan slot rutin.
 *
 * `before`/`after` sengaja JSON (snapshot bebas-bentuk: pengajar_id +
 * NAMA-nya, kategori, ruangan, hari, jam, dst — apa pun yang relevan
 * untuk sumbernya) bukan kolom per-field — dua sumber di atas punya
 * bentuk data yang beda (rutin: hari+jam_mulai+durasi_menit; kelas:
 * start_time+end_time), dan objek asalnya (JadwalRutin lama, terutama)
 * bisa sudah TERHAPUS PERMANEN di titik manapun setelahnya (lihat
 * JadwalStudentController's blok reconciliation — JadwalRutin lama
 * memang di-hard-delete, bukan cuma dinonaktifkan), jadi baris log ini
 * HARUS berdiri sendiri (snapshot lengkap) bukan bergantung JOIN ke
 * baris yang sumbernya sendiri sudah tidak ada.
 *
 * `jadwal_kelas_id` cuma dipakai `source` 'jadwal_kelas_edit' (nullable
 * untuk 'student_form', karena perubahan di jalur itu terjadi di level
 * JadwalRutin, sebelum ada satu pun sesi baru yang relevan digenerate).
 * `student_id`/`branch_office_id` NULLABLE + nullOnDelete — baris log
 * historis TIDAK IKUT HILANG kalau murid/branch-nya nanti dihapus (pola
 * sama seperti jadwal_kelas_reminder_logs.jadwal_reminder_rule_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_change_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->foreignUuid('student_id')
                ->nullable()
                ->constrained('jadwal_student')
                ->nullOnDelete();

            $table->foreignUuid('jadwal_kelas_id')
                ->nullable()
                ->constrained('jadwal_kelas')
                ->nullOnDelete();

            // 'student_form' | 'jadwal_kelas_edit' -- lihat docblock class.
            $table->string('source', 30);

            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->foreignUuid('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['company_id', 'student_id']);
            $table->index(['jadwal_kelas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_change_logs');
    }
};
