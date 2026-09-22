<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur "Tagihan" (aplikasi pembayaran/invoice, diskusi 22 September
 * 2026) -- level paling atas dari rangkaian: Branch -> Category ->
 * Tagihan -> Penerima, pola drill-down yang sama persis dengan Form
 * (lihat create_form_categories_table.php's docblock) dan Jadwal.
 *
 * `branch_office_id` WAJIB, sama seperti form_categories -- setiap
 * category tagihan selalu milik SATU cabang, tidak ada konsep
 * "company-wide, semua cabang pakai" (permintaan user: "Konexa itu ada
 * branch atau cabang jadi form itu harus di bawah cabang" -- Tagihan
 * ikut pola yang sama).
 *
 * `company_id` didenormalisasi di sini (dan di semua tabel tagihan_*
 * turunannya) supaya query scoped-ke-company tidak perlu join balik ke
 * branch_offices tiap kali -- pola yang sama dipakai form_categories/
 * jadwal_mata_pelajaran.
 *
 * `is_recurring` murni metadata UI (nentuin apakah halaman category ini
 * menampilkan daftar langganan pelanggan lewat tagihan_category_pelanggan
 * atau tidak) -- TIDAK memicu pembuatan Tagihan otomatis lewat job
 * terjadwal. Admin tetap yang membuat tiap baris tagihan_category secara
 * manual per periode (konfirmasi user, contoh: "uang sekolah januari
 * 2026, uang sekolah februari 2026" dibuat satu-satu) -- yang otomatis
 * cuma DAFTAR PENERIMANYA, diambil dari tagihan_category_pelanggan yang
 * sudah dicentang, bukan tagihan-nya sendiri.
 *
 * `denda_enabled` + `default_amount` adalah nilai DEFAULT yang dipakai
 * saat admin membuat Tagihan baru di bawah category ini -- App\Models\
 * Tagihan tetap simpan salinannya sendiri (amount, pakai_denda) supaya
 * tagihan yang sudah dibuat tidak berubah kalau default category-nya
 * diedit belakangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_category', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'tagihan_cat_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'tagihan_cat_branch_fk')
                ->cascadeOnDelete();

            $table->string('name', 255);

            // Murni metadata UI -- lihat docblock di atas. Tidak memicu
            // job otomatis apa pun.
            $table->boolean('is_recurring')->default(false);

            $table->decimal('default_amount', 15, 2)->nullable();

            // Toggle default -- lihat App\Models\TagihanDendaTier untuk
            // aturan sebenarnya. Category boleh punya baris tier tanpa
            // ini aktif (disimpan tapi tidak dipakai default), dan
            // sebaliknya tidak boleh aktif tanpa baris tier sama sekali
            // (divalidasi di controller, bukan di level DB).
            $table->boolean('denda_enabled')->default(false);

            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            $table->index(['company_id', 'branch_office_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_category');
    }
};
