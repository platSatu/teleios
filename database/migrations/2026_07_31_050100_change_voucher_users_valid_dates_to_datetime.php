<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Same fix as 2026_07_31_050000_change_vouchers_valid_dates_to_datetime
     * applied to voucher_users (promo codes) — Dashboard\
     * PackageCheckoutController::validatePromo() compared these against
     * Carbon::today() (date-only), which had the identical "expires at
     * midnight, not the actual hour" bug. valid_from/valid_until here
     * are required (never null), unlike vouchers'.
     */
    public function up(): void
    {
        Schema::table('voucher_users', function (Blueprint $table) {
            $table->dateTime('valid_from')->change();
            $table->dateTime('valid_until')->change();
        });
    }

    public function down(): void
    {
        Schema::table('voucher_users', function (Blueprint $table) {
            $table->date('valid_from')->change();
            $table->date('valid_until')->change();
        });
    }
};
