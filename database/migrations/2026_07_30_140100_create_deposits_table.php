<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\Deposit. reference_number/idempotency_key are
     * both unique — idempotency_key in particular exists so a retried
     * form submission (double-click, flaky network) can't create two
     * deposits for the same intended top-up.
     */
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // restrictOnDelete, same reasoning as wallets: a deposit is
            // a financial record, not something that should vanish
            // silently if the user row is ever removed.
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('reference_number')->unique();
            $table->uuid('idempotency_key')->nullable()->unique();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('IDR');

            $table->string('payment_method')->nullable();
            $table->string('payment_provider')->nullable();
            $table->string('provider_transaction_id')->nullable();

            // PENDING | SUCCESS | FAILED — today the app only ever
            // creates SUCCESS directly (manual top-up simulation), but
            // PENDING/FAILED are here for the real payment-gateway flow
            // (create PENDING, flip on webhook callback).
            $table->string('status', 20)->default('PENDING');

            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
