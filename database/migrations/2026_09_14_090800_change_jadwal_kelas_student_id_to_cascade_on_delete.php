<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan user (4 September 2026, laporan "fungsi delete di table
 * student tidak berfungsi"): tombol Hapus di App\Http\Controllers\
 * Jadwal\JadwalStudentController::destroy() SELALU gagal kalau murid
 * itu sudah punya sesi (App\Models\JadwalKelas) -- FK
 * `jadwal_kelas.student_id` dibuat `restrictOnDelete()` sejak migration
 * aslinya (create_jadwal_kelas_table.php, 31 Agustus 2026), jadi MySQL
 * menolak hapus baris JadwalStudent manapun yang sudah punya sesi sama
 * sekali (hampir selalu ada, karena sesi otomatis ter-generate begitu
 * jadwal dicentang) -- destroy() juga tidak ada try/catch, jadi
 * gagalnya berupa error mentah (bukan pesan yang rapi).
 *
 * User memutuskan (setelah didiskusikan) bahwa aksi Hapus ini memang
 * dimaksudkan sebagai "Hapus Total" -- SELURUH rangkaian data murid
 * ini terhapus permanen SEKALIGUS, termasuk histori sesi & perhitungan
 * komisinya (`harga_sesi`/`persentase_company`/`persentase_pengajar`
 * yang nempel di tiap baris JadwalKelas) -- supaya tidak ada data
 * "nyangkut" yang bikin bingung (murid sudah dihapus tapi datanya
 * masih ada). Untuk kasus yang TIDAK ingin datanya ikut hilang, sudah
 * ada aksi terpisah "Nonaktifkan" (status=inactive, TIDAK menghapus
 * apa pun, lihat JadwalStudentController::deactivate() & filter murid
 * aktif di App\Services\Jadwal\JadwalLaporanService::rekap()) -- Hapus
 * Total sekarang murni untuk data yang MEMANG ingin dibuang habis
 * (mis. data uji coba).
 *
 * Diubah dari restrictOnDelete() ke cascadeOnDelete() supaya
 * DB::delete() pada JadwalStudent otomatis merambat: JadwalStudent ->
 * jadwal_rutin (sudah cascadeOnDelete sejak awal) -> jadwal_kelas
 * (BARU diubah di sini) -> jadwal_kelas_reminder_logs (sudah
 * cascadeOnDelete) ikut lenyap juga. Tabel lain yang FK ke jadwal_kelas
 * (jadwal_kelas_reschedule_requests, jadwal_change_logs,
 * jadwal_kelas.pengganti_dari_sesi_id) semuanya nullOnDelete --
 * SENGAJA TIDAK diubah jadi cascade: keduanya murni catatan/audit-trail
 * historis (permintaan/riwayat request reschedule yang sudah pernah
 * diproses, dan jejak audit perubahan jadwal -- lihat docblock
 * App\Models\JadwalChangeLog), bukan bagian dari "rangkaian aktif"
 * murid yang harus ikut lenyap begitu muridnya dihapus.
 *
 * down() SENGAJA mengembalikan ke restrictOnDelete() -- perilaku
 * default project ini SEBELUM bug ini ditemukan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nama constraint eksplisit ('jadwal_kelas_student_id_foreign')
        // -- pola sama dengan migration korektif FK student_id
        // sebelumnya (2026_09_01_090000_fix_..., 2026_09_14_090100_make_
        // student_id_nullable_...) supaya konsisten & tidak bergantung
        // pada tebakan nama default Laravel.
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropForeign('jadwal_kelas_student_id_foreign');
        });

        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->foreign('student_id', 'jadwal_kelas_student_id_foreign')
                ->references('id')->on('jadwal_student')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->dropForeign('jadwal_kelas_student_id_foreign');
        });

        Schema::table('jadwal_kelas', function (Blueprint $table) {
            $table->foreign('student_id', 'jadwal_kelas_student_id_foreign')
                ->references('id')->on('jadwal_student')
                ->restrictOnDelete();
        });
    }
};
