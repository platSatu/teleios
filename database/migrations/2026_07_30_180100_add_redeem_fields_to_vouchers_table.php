<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends the existing per-user `vouchers` table so it can also work
     * as a purchase-generated "activation code": bought via Dashboard\
     * PackageCheckoutController, then redeemed later via Dashboard\
     * VoucherRedeemController (a separate, deliberate step — valid_from/
     * valid_until are only stamped in at redeem time, based on the
     * package's `duration`, not at purchase time).
     *
     * valid_from/valid_until become nullable because a freshly-purchased,
     * not-yet-redeemed voucher has no validity window yet. Superadmin's
     * existing manual voucher CRUD (Superadmin\VoucherController) still
     * requires both on its own form, so this doesn't change that flow.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignUuid('package_id')
                ->nullable()
                ->after('user_id')
                ->constrained('packages')
                ->nullOnDelete();

            $table->foreignUuid('subscription_id')
                ->nullable()
                ->after('package_id')
                ->constrained('subscriptions')
                ->nullOnDelete();

            $table->dateTime('redeemed_at')->nullable()->after('valid_until');

            $table->date('valid_from')->nullable()->change();
            $table->date('valid_until')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropColumn('redeemed_at');
            $table->date('valid_from')->nullable(false)->change();
            $table->date('valid_until')->nullable(false)->change();
        });
    }
};
