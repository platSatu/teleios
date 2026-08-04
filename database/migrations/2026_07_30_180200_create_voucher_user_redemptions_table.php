<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Usage log for App\Models\VoucherUser (the shared/promo codes) —
     * one row per successful redemption at checkout. Needed to enforce
     * `voucher_users.limit` (total redemptions across everyone) and
     * `voucher_users.use_by_user` (redemptions by this one user) in
     * Dashboard\PackageCheckoutController, since the voucher_users row
     * itself only holds the rules, not a running count.
     */
    public function up(): void
    {
        Schema::create('voucher_user_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('voucher_user_id')
                ->constrained('voucher_users')
                ->cascadeOnDelete();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Nullable + nullOnDelete: keep the redemption count accurate
            // even if the underlying subscription record is ever removed.
            $table->foreignUuid('subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_user_redemptions');
    }
};
