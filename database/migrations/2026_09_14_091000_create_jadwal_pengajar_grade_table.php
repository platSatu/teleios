<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalPengajarGrade -- penugasan Pengajar (users) ke
 * satu App\Models\JadwalGrade. MENGGANTIKAN peran App\Models\
 * JadwalPengajarKategori (permintaan user 8 September 2026: penugasan
 * Pengajar + jam ketersediaan pindah dari level Kategori ke level Grade
 * yang baru) -- tabel `jadwal_pengajar_kategori` LAMA SENGAJA TIDAK
 * disentuh/dihapus sama sekali (tetap ada sebagai data historis/frozen,
 * lihat migration create_jadwal_grade_table.php's docblock untuk alasan
 * "jangan edit yang sudah pernah migrate"), kode baru mulai sekarang
 * SELALU baca/tulis tabel INI, bukan yang lama.
 *
 * unique(jadwal_grade_id, pengajar_id) -- satu pengajar cuma bisa punya
 * SATU baris penugasan per Grade (pola identik jadwal_pengajar_kategori).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pengajar_grade', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('jadwal_grade_id')
                ->constrained('jadwal_grade')
                ->cascadeOnDelete();

            $table->foreignUuid('pengajar_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // 'active' | 'inactive'
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->unique(['jadwal_grade_id', 'pengajar_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pengajar_grade');
    }
};
