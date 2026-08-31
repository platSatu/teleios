<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalStudent — satu baris murid, letaknya di antara
 * App\Models\JadwalMataPelajaran/pengajar dan App\Models\JadwalKelas
 * dalam alur drill-down: Branch -> Mata Pelajaran / Bidang -> Pengajar
 * -> Student -> Jadwal (lihat App\Http\Controllers\Jadwal\*, semuanya
 * mengikuti pola "ina" project's University -> Album -> Photo: index
 * di-scope lewat query string id parent, tombol "+ Add <level
 * berikutnya>" membawa id itu, form create-nya mengunci field yang
 * sudah punya konteks).
 *
 * Beda dari desain awal jadwal_kelas.student_id (yang langsung FK ke
 * `users`, per spec awal "ambil dari table user") — sekarang Student
 * adalah entitas rosternya sendiri (`name` bebas, TIDAK harus py akun
 * login), bukan lagi harus berupa user Laravel. jadwal_kelas.student_id
 * diubah lewat migration create_jadwal_kelas_table di atasnya (masih
 * sama-sama belum di-push, jadi aman diedit langsung di migration
 * aslinya alih-alih migration korektif baru) supaya FK-nya nunjuk ke
 * sini, bukan lagi ke `users`.
 *
 * `pengajar_id` di sini TETAP FK ke `users` (Pengajar tidak dapat tabel
 * baru sendiri — dipilih dari App\Http\Controllers\Concerns\
 * ResolvesCompanyContext::companyTeamMembers(), sama seperti sebelumnya)
 * — Student sekadar mencatat pengajar/mata-pelajaran mana yang jadi
 * konteksnya saat dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_student', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->foreignUuid('jadwal_mata_pelajaran_id')
                ->constrained('jadwal_mata_pelajaran')
                ->cascadeOnDelete();

            $table->foreignUuid('pengajar_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('name');

            // 'active' | 'inactive'
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['jadwal_mata_pelajaran_id']);
            $table->index(['pengajar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_student');
    }
};
