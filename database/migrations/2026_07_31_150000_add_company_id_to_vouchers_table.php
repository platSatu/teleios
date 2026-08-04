<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which company a voucher was purchased under. Nullable — existing
     * vouchers predate this column and have no company to backfill, and
     * superadmin-created vouchers (Superadmin\VoucherController) still
     * don't require one. Going forward, Dashboard\PackageCheckoutController
     * always sets it, since a company is now required before checkout
     * (see the accompanying gate added there).
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignUuid('company_id')
                ->nullable()
                ->after('user_id')
                ->constrained('companies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
