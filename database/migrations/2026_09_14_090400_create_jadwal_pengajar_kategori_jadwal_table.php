<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalPengajarJadwal — SLOT ketersediaan seorang
 * pengajar untuk satu App\Models\JadwalPengajarKategori (penugasan
 * pengajar ke Kategori). Satu baris = SATU hari + SATU rentang jam.
 *
 * Dipecah dari `jadwal_pengajar_kategori` (permintaan user 3 September
 * 2026, lihat CLAUDE.md item #15's addendum terbaru) karena kasus
 * lapangan: pengajar TIDAK available nonstop pagi-sore seperti jam
 * kantor -- satu hari bisa punya lebih dari satu rentang jam (mis.
 * Senin 10:00-12:00 lalu 17:00-19:00 di hari yang sama), dan tiap hari
 * bisa punya jam yang beda-beda. Solusinya: satu
 * `jadwal_pengajar_kategori_id` boleh punya BANYAK baris di sini,
 * termasuk BANYAK baris di hari yang sama -- tinggal "tambah baris" di
 * form (lihat resources/views/jadwal/jadwal-pengajar/_form.blade.php),
 * bukan satu kolom hari_bisa + satu jam_mulai/jam_selesai yang berlaku
 * sama ke semua hari seperti versi sebelumnya.
 *
 * Murni info ketersediaan (ditampilkan di form Add Student, lihat
 * App\Http\Controllers\Jadwal\JadwalStudentController::create()) --
 * TIDAK divalidasi silang ke App\Models\JadwalRutin, validasi bentrok
 * jadwal tetap 100% di App\Services\Jadwal\JadwalRutinConflictService.
 *
 * `hari` konvensi SAMA dengan App\Models\JadwalRutin::HARI_LABELS /
 * JadwalBranchSetting::hari_operasional (Carbon::dayOfWeek,
 * 0=Minggu..6=Sabtu).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pengajar_kategori_jadwal', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('jadwal_pengajar_kategori_id')
                ->constrained('jadwal_pengajar_kategori')
                ->cascadeOnDelete();

            // Carbon::dayOfWeek: 0=Minggu, 1=Senin, ... 6=Sabtu. SATU
            // angka per baris (bukan array) -- boleh berulang di hari
            // yang sama kalau pengajarnya punya lebih dari satu
            // rentang jam di hari itu.
            $table->unsignedTinyInteger('hari');

            $table->time('jam_mulai');
            $table->time('jam_selesai');

            $table->timestamps();

            $table->index(['jadwal_pengajar_kategori_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pengajar_kategori_jadwal');
    }
};
