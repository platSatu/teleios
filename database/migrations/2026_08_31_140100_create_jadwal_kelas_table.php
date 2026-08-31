<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalKelas — satu baris jadwal kelas kursus: siapa
 * pengajarnya, siapa muridnya, kapan mulai/selesai. Sengaja 1 pengajar +
 * 1 murid per baris (bukan roster banyak murid seperti modul "Jadwal"
 * lama yang sudah dihapus — lihat docblock migration
 * create_jadwal_mata_pelajaran_table di atasnya) sesuai spec yang
 * diminta; kelas dengan banyak murid berarti beberapa baris.
 *
 * `pengajar_id` FK ke `users` (dipilih dari App\Http\Controllers\
 * Concerns\ResolvesCompanyContext::companyTeamMembers(), sama seperti
 * modul lain). `student_id` FK ke `jadwal_student` (BUKAN `users` lagi
 * seperti desain awal) — Student sekarang entitas roster sendiri (lihat
 * migration create_jadwal_student_table.php, dijalankan sebelum
 * migration ini justru supaya FK ini valid), supaya seorang murid tidak
 * harus punya akun login untuk bisa dijadwalkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_kelas', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->foreignUuid('jadwal_mata_pelajaran_id')
                ->nullable()
                ->constrained('jadwal_mata_pelajaran')
                ->nullOnDelete();

            // Wajib diisi (tidak nullable) — restrictOnDelete supaya
            // user pengajar / baris jadwal_student tidak bisa terhapus
            // diam-diam selama masih punya jadwal kelas (harus
            // dipindah/dibatalkan dulu).
            $table->foreignUuid('pengajar_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUuid('student_id')
                ->constrained('jadwal_student')
                ->restrictOnDelete();

            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();

            // 'active' | 'inactive'
            $table->string('status', 20)->default('active');

            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['pengajar_id', 'start_time']);
            $table->index(['student_id', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_kelas');
    }
};
