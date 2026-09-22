<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris = satu App\Models\TagihanPelanggan yang kena satu
 * App\Models\Tagihan -- ini "invoice" sebenarnya yang orang bayar
 * (Tagihan sendiri cuma "batch/periode"-nya). Diisi otomatis begitu
 * admin membuat Tagihan baru (disalin dari daftar langganan aktif di
 * tagihan_category_pelanggan milik category-nya), tapi admin bisa
 * tambah/hapus baris di sini secara manual tanpa mengubah daftar
 * langganan di category (lihat docblock tagihan_category_pelanggan).
 *
 * `company_id`/`branch_office_id` didenormalisasi dari Tagihan induknya
 * -- dipakai langsung oleh App\Http\Controllers\Tagihan\
 * PublicTagihanController supaya URL publik (lihat `public_token` di
 * bawah) bisa di-resolve tanpa join balik ke tagihan/tagihan_category,
 * dan supaya slug cabang di URL bisa divalidasi cocok dengan baris ini.
 *
 * `amount` adalah SALINAN FINAL (lihat docblock create_tagihan_table.php
 * soal 3 lapis salinan amount) -- inilah nominal yang benar-benar
 * ditagihkan ke orang ini, terkunci sejak baris ini dibuat.
 *
 * `denda_amount` dihitung ulang (bukan trigger DB) tiap kali baris ini
 * dibaca/diakses di luar masa jatuh tempo -- App\Models\TagihanCategory's
 * tagihan_denda_tier jadi rumusnya. Disimpan (bukan cuma computed
 * property) supaya nominal yang sudah pernah ditagihkan/dibayar tidak
 * berubah retroaktif kalau tier dendanya diedit setelah orang bayar.
 *
 * `public_token` -- UUID acak terpisah dari `id` (dua-duanya sama-sama
 * UUID v4 tak tertebak, tapi dipisah supaya `id` boleh dipakai bebas di
 * URL admin/log tanpa jadi kredensial akses, sementara `public_token`
 * khusus jadi "kunci" halaman publik dan bisa diregenerasi kalau perlu
 * tanpa mengubah `id` baris ini). URL publiknya:
 * app.konexa.id/tagihan/{slug branch_office}/{public_token} -- lihat
 * App\Http\Controllers\Tagihan\PublicTagihanController (menyusul).
 *
 * `expires_at` -- pola yang sama persis dengan App\Models\Deposit:
 * diisi begitu invoice Duitku beneran dibuat (bukan saat baris ini
 * pertama kali ada), dipakai App\Console\Commands\ProcessTagihanExpiry
 * (menyusul, meniru ProcessDepositExpiry) buat menandai 'kadaluarsa'
 * kalau waktu bayarnya lewat tanpa konfirmasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_penerima', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('tagihan_id')
                ->constrained(table: 'tagihan', indexName: 'tagihan_penerima_tagihan_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('tagihan_pelanggan_id')
                ->constrained(table: 'tagihan_pelanggan', indexName: 'tagihan_penerima_pelanggan_fk')
                ->restrictOnDelete();

            $table->foreignUuid('company_id')
                ->constrained(table: 'companies', indexName: 'tagihan_penerima_company_fk')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained(table: 'branch_offices', indexName: 'tagihan_penerima_branch_fk')
                ->cascadeOnDelete();

            $table->decimal('amount', 15, 2);
            $table->decimal('denda_amount', 15, 2)->default(0);

            // belum_bayar | lunas | kadaluarsa | dibatalkan
            $table->string('status', 20)->default('belum_bayar');

            $table->uuid('public_token')->unique();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->unique(['tagihan_id', 'tagihan_pelanggan_id'], 'tagihan_penerima_unique');
            $table->index(['company_id', 'branch_office_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_penerima');
    }
};
