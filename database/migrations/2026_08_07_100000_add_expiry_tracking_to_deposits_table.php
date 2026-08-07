<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the Duitku payment-window expiry flow:
 *
 * - `expires_at` — when the current Duitku invoice for this deposit
 *   stops accepting payment. Set once, in DepositController::
 *   proceedToDuitku(), from services.duitku.expiry_minutes (the same
 *   value already sent to Duitku as `expiryPeriod` when the invoice is
 *   created — kept in sync so our own expiry check always matches what
 *   Duitku itself is enforcing). Stays NULL for deposits that never
 *   reached the Duitku invoice step (e.g. cancelled at the checkout
 *   confirmation page before "Lanjutkan ke Duitku").
 *
 * - `reminder_sent_at` — idempotency stamp for the "segera selesaikan
 *   pembayaran Anda" reminder email (App\Console\Commands\
 *   ProcessDepositExpiry). Duitku never pushes a native "expired"
 *   callback, so both the reminder and the eventual EXPIRED transition
 *   are driven entirely by this app's own scheduled check against
 *   `expires_at` — these two columns are what make that check
 *   idempotent (never double-send the reminder, never re-process an
 *   already-expired deposit).
 *
 * No new `status` value needs a schema change — `deposits.status` is a
 * plain string column (see 2026_07_30_140100_create_deposits_table),
 * so 'EXPIRED' is introduced purely at the application level, alongside
 * the existing PENDING/SUCCESS/FAILED.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('paid_at');
            $table->timestamp('reminder_sent_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'reminder_sent_at']);
        });
    }
};
