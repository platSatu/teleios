<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalMataPelajaran — katalog "Mata Pelajaran /
 * Bidang" milik fitur Jadwal (mis. Piano, Bahasa Inggris, Vokal, dst.),
 * generik lintas bidang pendidikan, bukan hardcode ke satu jenis kursus.
 * App\Models\JadwalKelas menunjuk ke sini secara opsional.
 *
 * Catatan: sebuah modul "Jadwal" pernah ada sebelumnya di app ini (jauh
 * lebih kompleks — jadwal mingguan berulang, roster banyak murid,
 * absensi per sesi, paket berbayar terpisah) dan sudah dihapus permanen
 * 2026-08-21 (lihat migration 2026_08_21_100000_drop_jadwal_kelas_module,
 * "tidak ada company yang masih subscribe/pakai"). Ini adalah desain
 * baru yang sengaja jauh lebih sederhana (1 pengajar + 1 murid per baris
 * jadwal_kelas, tanpa paket berbayar) — nama tabel/route "jadwal.*" aman
 * dipakai ulang karena sudah di-drop bersih.
 *
 * `company_id` (wajib) + `branch_office_id` (nullable) dipakai alih-alih
 * `branch_id` seperti yang diminta di brief awal — mengikuti pola setiap
 * resource company-owned lain di app ini (wa_category_phone_book,
 * wa_customer_tasks, dst.), semuanya menunjuk ke `branch_offices`.`id`,
 * dan tetap butuh `company_id` supaya bisa discope lewat
 * App\Services\Company\CompanyContext seperti modul lain — lihat
 * App\Http\Controllers\Jadwal\JadwalMataPelajaranController.
 *
 * `image` menyimpan path RELATIF terhadap public/jadwal (bukan lewat
 * disk storage/app/public + symlink yang dipakai Company::logo) — lihat
 * App\Helpers\JadwalImageUploader.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_mata_pelajaran', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            // Path relatif terhadap public/jadwal, mis.
            // "mata-pelajaran/9c1e...-a1b2.jpg" — lihat
            // App\Helpers\JadwalImageUploader::upload()/url()/delete().
            $table->string('image')->nullable();

            // 'active' | 'inactive'
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_mata_pelajaran');
    }
};
