<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalPengajarKategori — penugasan Pengajar (users)
 * ke satu App\Models\JadwalKategori, dengan ketersediaan hari & jam
 * ngajarnya SENDIRI untuk kategori itu (bukan berlaku global ke semua
 * kategori yang dia ajar). Level BARU di drill-down Jadwal (restrukturisasi
 * 14 September 2026 atas permintaan user, lihat diagram alur & CLAUDE.md
 * item #15): Branch -> Ruangan -> Jam Operasional -> Mata Pelajaran /
 * Bidang -> Kategori -> **Pengajar** -> Student.
 *
 * Sebelumnya "Pengajar" di alur ini cuma halaman pilih App\Models\User
 * yang sudah ada (read-only, tanpa tabel sendiri, lihat App\Http\
 * Controllers\Jadwal\JadwalPengajarController versi lama). Sekarang jadi
 * entitas sendiri supaya admin bisa mencatat SIAPA mengajar KATEGORI
 * mana + hari/jam berapa dia available, sebelum lanjut ke "+ Add
 * Student" (form Add Student menampilkan ketersediaan ini, murni info,
 * TIDAK divalidasi silang ke App\Models\JadwalRutin -- validasi bentrok
 * jadwal tetap di JadwalRutinConflictService seperti sebelumnya, baris
 * ini tidak ikut campur di situ).
 *
 * `hari_bisa` JSON array of int, konvensi SAMA dengan
 * App\Models\JadwalRutin::HARI_LABELS / JadwalBranchSetting::hari_operasional
 * (Carbon::dayOfWeek, 0=Minggu..6=Sabtu) supaya tidak ada konvensi
 * angka hari yang beda-beda di fitur Jadwal.
 *
 * unique(jadwal_kategori_id, pengajar_id) -- satu pengajar cuma bisa
 * punya SATU baris ketersediaan per Kategori (edit baris yang sudah ada
 * kalau mau ubah hari/jam, bukan duplikat baris baru).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pengajar_kategori', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('jadwal_kategori_id')
                ->constrained('jadwal_kategori')
                ->cascadeOnDelete();

            // FK ke `users`, sama konvensi dengan pengajar_id di
            // jadwal_kelas/jadwal_rutin/jadwal_student -- pengajar TETAP
            // user perusahaan yang sudah ada, bukan tabel roster
            // terpisah (beda dari student_id yang ke jadwal_student).
            $table->foreignUuid('pengajar_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Carbon::dayOfWeek: 0=Minggu, 1=Senin, ... 6=Sabtu.
            $table->json('hari_bisa');

            $table->time('jam_mulai');
            $table->time('jam_selesai');

            // 'active' | 'inactive'
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->unique(['jadwal_kategori_id', 'pengajar_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pengajar_kategori');
    }
};
