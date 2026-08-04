<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per successful wallet-to-wallet transfer between two
     * users (Dashboard\WalletTransferController::store()). This is the
     * user-facing "who sent, who received, how much, balance before/
     * after for BOTH sides" record — a level up from the generic
     * LedgerEntry rows the transfer's debit()/credit() calls also
     * produce (those are per-wallet, this one ties the pair together
     * for history display).
     *
     * restrictOnDelete on both user FKs: a transfer record is a
     * financial fact that must never silently disappear just because
     * one side's account was later deleted.
     */
    public function up(): void
    {
        Schema::create('wallet_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('sender_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUuid('sender_wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignUuid('receiver_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignUuid('receiver_wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();

            $table->decimal('sender_balance_before', 12, 2);
            $table->decimal('sender_balance_after', 12, 2);
            $table->decimal('receiver_balance_before', 12, 2);
            $table->decimal('receiver_balance_after', 12, 2);

            // SUCCESS | FAILED — a row is only ever written after the
            // transfer already completed (or definitively failed inside
            // the same DB transaction), never left PENDING.
            $table->string('status', 20)->default('SUCCESS');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transfers');
    }
};
