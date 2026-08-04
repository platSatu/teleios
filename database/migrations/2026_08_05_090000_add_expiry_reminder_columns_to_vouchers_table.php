<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dedup tracking for App\Console\Commands\SendPackageExpiryReminders:
     * one nullable timestamp per reminder milestone (H-7 / H-3 / H-1 /
     * H-0, i.e. the day valid_until itself falls on), stamped the moment
     * that milestone's email is actually queued for a given voucher.
     *
     * Four separate columns instead of one JSON blob so "has H-3 already
     * been sent for this voucher" stays a plain indexed WHERE ... IS NULL
     * check, not a JSON_CONTAINS — same reasoning as this table's other
     * flat timestamp columns (redeemed_at, valid_from/until).
     *
     * Scoped to a single voucher row on purpose: a renewal buys/redeems a
     * NEW voucher row that chains on top (see VoucherRedeemController's
     * $previousActive logic) rather than mutating this one, so once a
     * later-expiring active voucher exists for the same user+package,
     * SendPackageExpiryReminders treats THIS voucher as superseded and
     * stops reminding for it — these columns don't need to know about
     * that, they only ever answer "did *this* voucher's H-N reminder go
     * out already".
     */
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->timestamp('reminder_7d_sent_at')->nullable()->after('redeemed_at');
            $table->timestamp('reminder_3d_sent_at')->nullable()->after('reminder_7d_sent_at');
            $table->timestamp('reminder_1d_sent_at')->nullable()->after('reminder_3d_sent_at');
            $table->timestamp('reminder_0d_sent_at')->nullable()->after('reminder_1d_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn([
                'reminder_7d_sent_at',
                'reminder_3d_sent_at',
                'reminder_1d_sent_at',
                'reminder_0d_sent_at',
            ]);
        });
    }
};
