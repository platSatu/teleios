<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\Wallet. One wallet per user per currency — the
     * app currently only ever creates/uses IDR wallets, but the
     * `currency` column exists on the model already, so the unique
     * constraint is on (user_id, currency) rather than user_id alone
     * to leave that door open without a future migration.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // restrictOnDelete: a wallet carries real balance/ledger
            // history — deleting the owning user should fail loudly
            // rather than silently orphaning or wiping financial data.
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('currency', 10)->default('IDR');
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('status', 20)->default('active'); // active | inactive

            $table->timestamps();

            $table->unique(['user_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
