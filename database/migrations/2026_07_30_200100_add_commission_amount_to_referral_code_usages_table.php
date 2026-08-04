<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the actual rupiah commission credited to the referrer for
     * this usage row. Kept as a snapshot rather than recomputed later
     * from discount_percent * subscription.amount, because
     * referral_codes.percentage can be edited by superadmin after the
     * fact — this column preserves what was actually paid out at the
     * time, so history stays accurate even if the rate changes later.
     */
    public function up(): void
    {
        Schema::table('referral_code_usages', function (Blueprint $table) {
            $table->decimal('commission_amount', 12, 2)->default(0)->after('discount_percent');
        });
    }

    public function down(): void
    {
        Schema::table('referral_code_usages', function (Blueprint $table) {
            $table->dropColumn('commission_amount');
        });
    }
};
