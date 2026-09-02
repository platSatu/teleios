<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalRutin -- "cetakan" jadwal mingguan berulang
 * milik SATU murid (App\Models\JadwalStudent): Kategori + Pengajar +
 * Ruangan (opsional) + Hari + Jam mulai + Durasi. Menggantikan
 * konsep lama "1 mata-pelajaran+1 pengajar per JadwalStudent" -- satu
 * murid sekarang boleh punya BANYAK baris Jadwal Rutin sekaligus
 * (mis. Senin Piano Classic, Selasa Drum Reguler) -- lihat spec
 * Jadwal v2 poin 4 di CLAUDE.md.
 *
 * App\Console\Commands\GenerateJadwalRutinSesi membaca baris-baris di
 * sini tiap bulan untuk generate baris App\Models\JadwalKelas
 * bertanggal (lihat kolom jadwal_rutin_id yang ditambahkan ke
 * jadwal_kelas oleh migration add_jadwal_v2_columns_to_jadwal_kelas_
 * table).
 *
 * Validasi bentrok pengajar & ruangan dicek di
 * App\Http\Controllers\Jadwal\JadwalRutinController SAAT baris ini
 * dibuat/disimpan (bukan nanti saat generate sesi) -- per user: "1
 * kelas 1 ruangan 1 guru jadi sifatnya private", jadi cukup 2 unique
 * index parsial (hari+jam_mulai per pengajar, dan per ruangan) yang
 * DICEK DI APP (bukan DB unique constraint biasa, karena perlu
 * overlap-time check bukan exact-match, dan perlu skip baris yang
 * sudah tidak efektif/inactive) -- index biasa di bawah ini murni
 * untuk performa query itu, bukan pemaksa constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_rutin', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            // Disimpan eksplisit (bukan cuma diturunkan dari student)
            // supaya query scoping-per-branch & conflict-check tidak
            // perlu join ke jadwal_student tiap kali.
            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->foreignUuid('student_id')
                ->constrained('jadwal_student')
                ->cascadeOnDelete();

            // restrictOnDelete: Kategori tidak boleh terhapus diam-diam
            // selama masih dipakai jadwal rutin aktif.
            $table->foreignUuid('jadwal_kategori_id')
                ->constrained('jadwal_kategori')
                ->restrictOnDelete();

            $table->foreignUuid('pengajar_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUuid('jadwal_ruangan_id')
                ->nullable()
                ->constrained('jadwal_ruangan')
                ->nullOnDelete();

            // Carbon::dayOfWeek: 0=Minggu, 1=Senin, ... 6=Sabtu -- sama
            // persis dengan konvensi jadwal_branch_settings.hari_operasional.
            $table->unsignedTinyInteger('hari');

            $table->time('jam_mulai');

            // Null = pakai durasi_sesi_default_menit dari
            // jadwal_branch_settings milik branch murid ini.
            $table->unsignedSmallInteger('durasi_menit')->nullable();

            // Sejak tanggal berapa jadwal rutin ini mulai berlaku --
            // generator sesi tidak akan generate sesi sebelum tanggal
            // ini walau hari-nya cocok.
            $table->date('efektif_mulai');

            // Null = masih berlaku terus (belum ada tanggal berhenti).
            $table->date('efektif_selesai')->nullable();

            // 'active' | 'inactive' -- nonaktifkan tanpa hapus data
            // historis jadwal_kelas yang sudah pernah digenerate dari
            // baris ini.
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['student_id']);
            $table->index(['pengajar_id', 'hari']);
            $table->index(['jadwal_ruangan_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_rutin');
    }
};
