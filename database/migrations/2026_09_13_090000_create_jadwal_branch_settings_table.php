<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalBranchSetting — "Jam Operasional" satu baris
 * PER BRANCH (unique branch_office_id): hari buka, jam buka/tutup, jam
 * istirahat, plus 2 angka default yang dipakai generator sesi bulanan
 * (App\Console\Commands\GenerateJadwalRutinSesi) supaya TIDAK ada yang
 * hardcode di kode — durasi_sesi_default_menit & sesi_per_bulan_default
 * (lihat spec "Jadwal v2" poin 1 & 6 di CLAUDE.md). Bagian dari
 * pengembangan besar Modul Jadwal Lanjutan ("Jadwal v2") — lihat
 * CLAUDE.md item #15 untuk spesifikasi lengkap hasil diskusi dengan
 * user sebelum baris kode ini ditulis.
 *
 * `branch_office_id` WAJIB (tidak nullable) — jam operasional secara
 * definisi berbeda per lokasi fisik, beda dengan App\Models\
 * JadwalMataPelajaran yang boleh lintas-branch (branch_office_id
 * nullable = "berlaku di semua branch").
 *
 * `hari_operasional` JSON array of int, konvensi SAMA dengan
 * Carbon::dayOfWeek (0=Minggu .. 6=Sabtu) — dipakai juga oleh
 * jadwal_rutin.hari supaya tidak ada 2 konvensi angka hari yang beda di
 * satu fitur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_branch_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->unique()
                ->constrained('branch_offices')
                ->cascadeOnDelete();

            // Carbon::dayOfWeek: 0=Minggu, 1=Senin, ... 6=Sabtu.
            // Contoh Senin-Sabtu: [1,2,3,4,5,6]
            $table->json('hari_operasional');

            $table->time('jam_buka');
            $table->time('jam_tutup');

            $table->time('jam_istirahat_mulai')->nullable();
            $table->time('jam_istirahat_selesai')->nullable();

            // Dipakai sebagai default durasi satu sesi (menit) ketika
            // jadwal_rutin.durasi_menit tidak diisi manual, dan sebagai
            // acuan generator sesi bulanan.
            $table->unsignedSmallInteger('durasi_sesi_default_menit')->default(30);

            // Jumlah sesi reguler yang digenerate per bulan per
            // jadwal_rutin (default 4x/bulan, minggu ke-5 disisakan
            // untuk sesi pengganti -- lihat spec poin 6 & 8).
            $table->unsignedTinyInteger('sesi_per_bulan_default')->default(4);

            // 'active' | 'inactive'
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_branch_settings');
    }
};
