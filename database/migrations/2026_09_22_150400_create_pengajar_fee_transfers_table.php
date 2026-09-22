<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur "Transfer Fee" bulanan (diskusi 22 September 2026) -- satu baris
 * per KLIK tombol "Transfer Fee" untuk satu BranchOffice + satu periode
 * bulan. App\Models\JadwalKelas yang ikut dibayarkan lewat transfer ini
 * ditandai lewat kolom baru jadwal_kelas.fee_transfer_id (lihat
 * migration add_fee_transfer_id_to_jadwal_kelas_table.php) -- itu
 * sekaligus penjaga idempotency-nya: sesi yang fee_transfer_id-nya
 * SUDAH terisi tidak akan pernah muncul lagi di preview/eksekusi
 * berikutnya, jadi tidak mungkin satu sesi dibayar dua kali walau
 * "Transfer Fee" diklik berulang untuk bulan yang sama.
 *
 * TIDAK ada tabel item terpisah (pengajar_fee_transfer_items) --
 * breakdown per pengajar cukup di-derive dengan GROUP BY jadwal_kelas
 * WHERE fee_transfer_id = ini, tidak perlu didenormalisasi ke tabel
 * lain (lihat App\Services\Wallet\PengajarFeeTransferService).
 *
 * total_debited di sini adalah SUM feePengajar() seluruh sesi yang ikut
 * -- jumlah yang didebit dari Wallet Branch, BUKAN termasuk porsi
 * persentase_company (itu memang sengaja tetap di Wallet Branch,
 * bukan dipindah kemana pun).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pengajar_fee_transfers')) {
            return;
        }

        Schema::create('pengajar_fee_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->restrictOnDelete();

            $table->foreignUuid('branch_office_id')
                ->constrained('branch_offices')
                ->restrictOnDelete();

            $table->foreignUuid('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('periode_year');
            $table->unsignedTinyInteger('periode_month');

            $table->decimal('total_debited', 15, 2)->default(0);
            $table->unsignedInteger('pengajar_count')->default(0);
            $table->unsignedInteger('sesi_count')->default(0);

            $table->foreignUuid('executed_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            // Satu branch cuma boleh punya SATU transfer sukses per
            // periode -- klik ganda "Transfer Fee" bulan yang sama tetap
            // aman berkat guard fee_transfer_id di jadwal_kelas (baris
            // baru itu akan preview 0 sesi/Rp0), tapi constraint ini
            // menutup celah race dua klik nyaris bersamaan yang sama-sama
            // lolos preview sebelum salah satu commit duluan.
            $table->unique(['branch_office_id', 'periode_year', 'periode_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajar_fee_transfers');
    }
};
