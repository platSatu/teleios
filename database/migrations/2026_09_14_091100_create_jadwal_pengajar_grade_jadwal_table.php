<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalPengajarGradeJadwal -- SLOT ketersediaan
 * seorang pengajar untuk satu App\Models\JadwalPengajarGrade (penugasan
 * pengajar ke Grade). Satu baris = SATU hari + SATU rentang jam --
 * struktur & alasan IDENTIK App\Models\JadwalPengajarJadwal (tabel
 * jadwal_pengajar_kategori_jadwal) yang lama, cuma parent-nya sekarang
 * Grade, bukan Kategori. Lihat docblock model lama itu untuk alasan
 * lengkap kenapa dipecah jadi banyak baris (pengajar tidak available
 * nonstop pagi-sore, satu hari bisa punya lebih dari satu rentang jam).
 *
 * Nama constraint FK & index SENGAJA dipendekkan manual (`jpgj_...`) --
 * pola sama seperti jadwal_pengajar_kategori_jadwal (nama tabel yang
 * kepanjangan bisa bikin identifier auto-generate Laravel lebih dari 64
 * karakter, limit MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pengajar_grade_jadwal', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('jadwal_pengajar_grade_id')
                ->constrained('jadwal_pengajar_grade', indexName: 'jpgj_pengajar_grade_id_fk')
                ->cascadeOnDelete();

            // Carbon::dayOfWeek: 0=Minggu, 1=Senin, ... 6=Sabtu.
            $table->unsignedTinyInteger('hari');

            $table->time('jam_mulai');
            $table->time('jam_selesai');

            $table->timestamps();

            $table->index(['jadwal_pengajar_grade_id', 'hari'], 'jpgj_pengajar_grade_id_hari_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pengajar_grade_jadwal');
    }
};
