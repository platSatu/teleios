<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo ditahan saat pengajuan tarik saldo (lihat WalletWithdrawalService):
 * held_at = saldo sudah dipotong untuk permintaan ini, refunded_at = sudah
 * dikembalikan. Baris lama (held_at null) ditahan saat disetujui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_withdrawals', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_withdrawals', 'held_at')) {
                $table->timestamp('held_at')->nullable()->after('status');
            }
            if (! Schema::hasColumn('wallet_withdrawals', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('held_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_withdrawals', function (Blueprint $table) {
            $table->dropColumn(['held_at', 'refunded_at']);
        });
    }
};
