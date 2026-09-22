<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitur Saldo Branch/Company/Reseller (Chat > diskusi 22 September
 * 2026) -- satu baris per permintaan "Tarik Saldo" ke rekening bank
 * lewat Duitku Disbursement (lihat App\Services\Payment\
 * DuitkuDisbursementService, dibuat menyusul migration ini). Dipakai
 * untuk SEMUA jenis penarik: pengajar/reseller menarik App\Models\
 * Wallet miliknya sendiri (wallet_id -> wallets.user_id), maupun
 * Company menarik Wallet milik salah satu App\Models\BranchOffice di
 * bawahnya (wallet_id -> wallets.branch_office_id) -- satu tabel yang
 * sama, cukup dibedakan lewat wallet yang dituju, TIDAK dua tabel
 * terpisah per jenis penarik.
 *
 * `company_id`/`branch_office_id` di sini SENGAJA didenormalisasi dari
 * wallet-nya (bukan cuma dilihat lewat wallet_id -> ...), supaya
 * dashboard approval ("siapa yang boleh approve permintaan ini") bisa
 * query langsung tanpa join ke wallets tiap kali -- resolusi
 * detailnya ada di App\Services\Wallet\WalletWithdrawalService.
 * Keduanya nullable karena Wallet milik reseller/pengajar yang tidak
 * terafiliasi ke company manapun (murni penerima komisi referral)
 * tetap sah minta tarik saldo tanpa company/branch context.
 *
 * `bank_code`/`bank_account`/`account_name` diisi ULANG tiap kali
 * request (keputusan user 22 September 2026: tidak ada rekening
 * tersimpan permanen di pengaturan Company/Branch) -- bukan snapshot
 * dari tempat lain.
 *
 * Status flow: pending_approval -> approved -> processing -> success
 * (atau -> failed kalau API Duitku menolak/error) -- atau
 * pending_approval -> rejected (admin/company owner menolak sebelum
 * sempat menyentuh Duitku sama sekali). `processing` sengaja jadi
 * status TERPISAH dari `approved` -- approved berarti "sudah disetujui
 * manusia, siap dieksekusi", processing berarti "sedang di tengah
 * panggilan inquiry+transfer Duitku", membedakan permintaan yang lama
 * disetujui-tapi-belum-diproses (stuck di background job) dari yang
 * beneran macet di tengah panggilan API.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_withdrawals')) {
            return;
        }

        Schema::create('wallet_withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            // Siapa yang MENGAJUKAN permintaan tarik saldo -- pemilik
            // Wallet sendiri (pengajar/reseller), atau admin
            // branch/owner company kalau yang ditarik Wallet Branch.
            $table->foreignUuid('requested_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUuid('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->decimal('amount', 15, 2);

            // Tujuan transfer -- diisi manual tiap request, lihat
            // docblock di atas. bank_code 3 digit (kode bank Duitku,
            // lihat App\Services\Payment\DuitkuDisbursementService::
            // listBanks()), bukan nama bank bebas.
            $table->string('bank_code', 10);
            $table->string('bank_account', 50);
            $table->string('account_name', 255);
            $table->string('purpose', 255)->nullable();

            $table->string('status', 20)->default('pending_approval');
            // pending_approval | approved | rejected | processing |
            // success | failed | cancelled

            $table->foreignUuid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Hasil panggilan Duitku Disbursement (inquiry lalu
            // transfer) -- custRefNumber WAJIB 9 digit menurut dokumen
            // Duitku, digenerate App\Services\Payment\
            // DuitkuDisbursementService, BUKAN input user.
            $table->string('duitku_disburse_id', 255)->nullable();
            $table->string('duitku_cust_ref_number', 20)->nullable();
            $table->json('duitku_response')->nullable();

            $table->timestamp('processed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['wallet_id', 'status']);
            $table->index(['company_id', 'status']);
            $table->index(['branch_office_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawals');
    }
};
