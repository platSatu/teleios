<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya Tarik Saldo (2 Oktober 2026), lihat WalletWithdrawalService:
 * - duitku_disbursement_settings.withdrawal_fee: biaya per penarikan, diisi superadmin.
 * - wallet_withdrawals.fee_amount / net_amount: snapshot biaya & jumlah yang
 *   dikirim ke rekening saat pengajuan (amount = saldo yang dipotong,
 *   net_amount = amount - fee_amount). Baris lama: fee 0, net = amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duitku_disbursement_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('duitku_disbursement_settings', 'withdrawal_fee')) {
                $table->decimal('withdrawal_fee', 15, 2)->default(0)->after('mode');
            }
        });

        Schema::table('wallet_withdrawals', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_withdrawals', 'fee_amount')) {
                $table->decimal('fee_amount', 15, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('wallet_withdrawals', 'net_amount')) {
                $table->decimal('net_amount', 15, 2)->nullable()->after('fee_amount');
            }
        });

        DB::table('wallet_withdrawals')->whereNull('net_amount')->update(['net_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('wallet_withdrawals', function (Blueprint $table) {
            $table->dropColumn(['fee_amount', 'net_amount']);
        });

        Schema::table('duitku_disbursement_settings', function (Blueprint $table) {
            $table->dropColumn('withdrawal_fee');
        });
    }
};
