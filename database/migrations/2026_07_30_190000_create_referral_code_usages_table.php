<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Usage log for App\Models\ReferralCode — one row per checkout where
     * a referral code was successfully applied. The referrer (code
     * owner) is reached via referral_code_id -> referral_codes.user_id,
     * so it isn't duplicated here; this table only needs to answer "who
     * used this code, for which purchase, and what discount did it give".
     *
     * Superadmin\PackageCheckoutController::validateReferral() already
     * refuses a code when referral_codes.user_id === the buyer's own id
     * (self-referral is blocked at validation time, before a row here is
     * ever created), so every row in this table is guaranteed to be a
     * genuinely different user from the code's owner.
     */
    public function up(): void
    {
        Schema::create('referral_code_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('referral_code_id')
                ->constrained('referral_codes')
                ->cascadeOnDelete();

            $table->foreignUuid('used_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();

            $table->decimal('discount_percent', 5, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_code_usages');
    }
};
