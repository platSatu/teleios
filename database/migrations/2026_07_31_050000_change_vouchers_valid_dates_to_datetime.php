<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fixes a real bug: vouchers.valid_from/valid_until were `date`
     * columns, so App\Http\Middleware\EnsureActivePackage's
     * `whereDate('valid_until', '>=', today)` check (and Dashboard\
     * VoucherRedeemController's precise `now()->addDays($days)` write)
     * both silently lost the time-of-day — every voucher expired at
     * midnight of its last day, not at the actual hour it was redeemed
     * plus its duration. A user redeeming at 14:00 for 1 day kept access
     * until the following midnight instead of the following 14:00.
     *
     * Existing rows: MySQL preserves the date portion and pads the time
     * to 00:00:00 — there's no way to recover the true original
     * redemption hour for vouchers already truncated by the old `date`
     * column, but every redemption from this point on will carry real
     * time-of-day precision.
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dateTime('valid_from')->nullable()->change();
            $table->dateTime('valid_until')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->date('valid_from')->nullable()->change();
            $table->date('valid_until')->nullable()->change();
        });
    }
};
