<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permanent referral link: set exactly once, the first time a user
     * successfully enters someone else's referral code at checkout (see
     * Dashboard\PackageCheckoutController::store()). From then on the
     * referrer earns commission on every future purchase this user
     * makes — no need to re-enter the code — until either the referral
     * code is blocked by superadmin or this user's own account goes
     * inactive.
     *
     * Nullable + nullOnDelete: most users have no referrer, and if a
     * referrer's account is ever deleted this just detaches the link
     * rather than deleting the referred user.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('referrer_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referrer_id');
        });
    }
};
