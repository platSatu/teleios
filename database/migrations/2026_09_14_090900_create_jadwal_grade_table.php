<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\JadwalGrade -- level BARU di bawah Kategori
 * (permintaan user 8 September 2026): Bidang/Mata Pelajaran -> Kategori
 * -> **Grade** -> Pengajar -> Student. Contoh konkret dari diskusi:
 * Bidang "Bass", Kategori "Jazz", Grade "A" (Rp 400rb), Grade "B"
 * (Rp 500rb), Grade "C" (Rp 600rb) -- grade lebih tinggi biasanya lebih
 * mahal, tapi angka pastinya bebas diisi admin per baris.
 *
 * Kategori SEBELUMNYA yang memegang harga_bulanan + persentase split +
 * jadi titik tempel penugasan Pengajar (App\Models\JadwalPengajarKategori)
 * -- SEKARANG tiga hal itu semua PINDAH ke sini. Kategori jadi murni
 * pengelompokan/nama gaya (mis. "Jazz"), tidak lagi punya harga sendiri.
 *
 * PENTING -- kontinuitas data lama: kolom harga_bulanan/persentase_*
 * di tabel `jadwal_kategori` SENGAJA TIDAK dihapus/disentuh sama sekali
 * di sini (lihat migration ini cuma CREATE tabel baru) -- per pelajaran
 * project ini (lihat CLAUDE.md "Fix 3 September 2026: kolom lama
 * hari_bisa..."), migration yang sudah pernah `php artisan migrate` di
 * lingkungan manapun tidak aman diedit langsung. Kategori LAMA yang
 * sudah ada otomatis dibuatkan SATU baris Grade default (migrasi data
 * terpisah, lihat migration backfill_default_jadwal_grade_for_existing_
 * kategori.php) yang membawa nilai harga/split lama itu APA ADANYA --
 * supaya kelas yang sedang rutin jalan sekarang (App\Models\JadwalRutin
 * aktif) tidak berhenti generate begitu fitur ini aktif. Kolom lama di
 * jadwal_kategori jadi tidak terpakai kode baru manapun setelah ini,
 * tapi dibiarkan ada (tidak ada yang baca lagi, aman dibiarkan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_grade', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('jadwal_kategori_id')
                ->constrained('jadwal_kategori')
                ->cascadeOnDelete();

            $table->string('name');

            // Harga BULANAN (bukan per sesi langsung) -- pola sama
            // persis dengan jadwal_kategori.harga_bulanan sebelumnya,
            // dibagi jumlah sesi/bulan branch lewat App\Models\
            // JadwalGrade::hargaPerSesi() (lihat App\Models\
            // JadwalKategori::hargaPerSesi() untuk method yang ditiru).
            $table->decimal('harga_bulanan', 12, 2);

            // Persentase split company + pengajar harus berjumlah 100 --
            // divalidasi di app (App\Http\Controllers\Jadwal\
            // JadwalGradeController), bukan DB check constraint, sama
            // pola dengan jadwal_kategori.
            $table->decimal('persentase_company', 5, 2);
            $table->decimal('persentase_pengajar', 5, 2);

            // 'active' | 'inactive'
            $table->string('status', 20)->default('active');

            $table->timestamps();

            $table->index(['company_id', 'jadwal_kategori_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_grade');
    }
};
