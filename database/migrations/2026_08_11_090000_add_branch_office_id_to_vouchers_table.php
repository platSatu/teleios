<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which branch office a voucher's package is activated for. Nullable,
     * same reasoning as vouchers.company_id (2026_07_31_150000): existing
     * vouchers predate this column and have nothing to backfill, and a
     * company that hasn't set up branch offices yet (or is on the legacy
     * single-branch flow) can keep buying/redeeming packages exactly as
     * before with this left null.
     *
     * Going forward this is set at REDEEM time (Dashboard\
     * VoucherRedeemController), not at purchase time (Dashboard\
     * PackageCheckoutController) — see that controller's docblock for why
     * "which branch this activates for" is deliberately decided later,
     * same moment as "when does the clock start" (valid_from).
     *
     * nullOnDelete (not cascade/restrict): deleting a branch office
     * shouldn't destroy the purchase/redemption history tied to it, and
     * shouldn't block the delete either — the voucher just reverts to
     * "not scoped to a branch" (still valid company-wide for whoever
     * reads it that way).
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->after('company_id')
                ->constrained('branch_offices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['branch_office_id']);
            $table->dropColumn('branch_office_id');
        });
    }
};
