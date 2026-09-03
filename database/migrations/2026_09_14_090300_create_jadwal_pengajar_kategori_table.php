<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalPengajarKategori — penugasan Pengajar (users)
 * ke satu App\Models\JadwalKategori. Level BARU di drill-down Jadwal
 * (restrukturisasi 14 September 2026 atas permintaan user, lihat
 * diagram alur & CLAUDE.md item #15): Branch -> Ruangan -> Jam
 * Operasional -> Mata Pelajaran / Bidang -> Kategori -> **Pengajar** ->
 * Student.
 *
 * Sebelumnya "Pengajar" di alur ini cuma halaman pilih App\Models\User
 * yang sudah ada (read-only, tanpa tabel sendiri, lihat App\Http\
 * Controllers\Jadwal\JadwalPengajarController versi lama). Sekarang jadi
 * entitas sendiri supaya admin bisa mencatat SIAPA mengajar KATEGORI
 * mana, sebelum lanjut ke "+ Add Student".
 *
 * Update 3 September 2026 (masih sesi yang sama, permintaan user):
 * `hari_bisa`/`jam_mulai`/`jam_selesai` DIPINDAH dari tabel ini ke
 * tabel anak `jadwal_pengajar_kategori_jadwal` (lihat migration
 * create_jadwal_pengajar_kategori_jadwal_table.php). Alasan: kasus
 * lapangan pengajar TIDAK available nonstop pagi-sore seperti jam
 * kantor -- satu hari bisa punya lebih dari satu rentang jam (mis.
 * Senin 10:00-12:00 lalu 17:00-19:00), dan tiap hari bisa punya jam
 * yang beda-beda, jadi tidak cukup ditampung 1 jam_mulai/jam_selesai
 * yang berlaku sama ke semua hari yang dicentang. Tabel ini SENGAJA
 * tetap belum pernah di-`php artisan migrate`-kan saat perubahan ini
 * dibuat, jadi kolom lama dihapus langsung di sini (bukan migration
 * alter terpisah) -- tidak ada data yang perlu dimigrasikan.
 *
 * unique(jadwal_kategori_id, pengajar_id) -- satu pengajar cuma bisa
 * punya SATU baris penugasan per Kategori (edit baris yang sudah ada
 * kalau mau ubah jadwalnya lewat tabel anak, bukan duplikat baris
 * baru).
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
