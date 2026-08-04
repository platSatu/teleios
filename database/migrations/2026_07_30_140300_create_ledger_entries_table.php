<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\LedgerEntry — the immutable double-entry line
     * (the model itself blocks update/delete at the Eloquent level and
     * hashes its own fields into entry_hash on create). balance_before
     * and balance_after are the literal "history sebelum dan sesudah"
     * (before/after balance) the superadmin deposit/wallet views need.
     * All three FKs are restrictOnDelete — nothing about a ledger
     * entry should ever silently disappear.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('transaction_id')
                ->constrained('ledger_transactions')
                ->restrictOnDelete();

            $table->foreignUuid('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('direction', 10); // CREDIT | DEBIT
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->string('entry_hash', 64);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
