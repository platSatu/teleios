<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan referral/reseller baru (lihat App\Services\Referral\ReferralService):
 *
 * referral_codes
 * - percentage jadi nullable: null = ikut default superadmin (Setting
 *   referral_commission_percent). Kode lama yang masih 20 (nilai bawaan
 *   dulu) diubah ke null supaya ikut default; yang sudah diubah khusus tetap.
 * - buyer_discount_amount: diskon Rupiah untuk customer di pembelian
 *   pertama, diambil dari komisi pemilik kode. null = ikut default.
 *
 * referral_code_usages
 * - buyer_discount_amount: diskon yang benar-benar diberikan.
 * - status pending (masa tahan/komplain) | available (sudah masuk wallet)
 *   | cancelled, beserta tanggalnya. Riwayat lama = available (sudah dibayar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table) {
            $table->decimal('percentage', 5, 2)->nullable()->default(null)->change();
            $table->decimal('buyer_discount_amount', 12, 2)->nullable()->after('percentage');
        });

        DB::table('referral_codes')->where('percentage', 20)->update(['percentage' => null]);

        Schema::table('referral_code_usages', function (Blueprint $table) {
            $table->decimal('buyer_discount_amount', 12, 2)->default(0)->after('discount_percent');
            $table->string('status', 20)->default('available')->after('commission_amount');
            $table->timestamp('available_at')->nullable()->after('status');
            $table->timestamp('credited_at')->nullable()->after('available_at');
            $table->timestamp('cancelled_at')->nullable()->after('credited_at');
            $table->string('cancel_reason')->nullable()->after('cancelled_at');
            $table->index(['status', 'available_at']);
        });

        DB::table('referral_code_usages')->update([
            'available_at' => DB::raw('created_at'),
            'credited_at' => DB::raw('CASE WHEN commission_amount > 0 THEN created_at ELSE NULL END'),
        ]);
    }

    public function down(): void
    {
        Schema::table('referral_code_usages', function (Blueprint $table) {
            $table->dropIndex(['status', 'available_at']);
            $table->dropColumn(['buyer_discount_amount', 'status', 'available_at', 'credited_at', 'cancelled_at', 'cancel_reason']);
        });

        DB::table('referral_codes')->whereNull('percentage')->update(['percentage' => 20]);

        Schema::table('referral_codes', function (Blueprint $table) {
            $table->dropColumn('buyer_discount_amount');
            $table->decimal('percentage', 5, 2)->default(20.00)->nullable(false)->change();
        });
    }
};
