<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\PaymentTransaction — the future payment-gateway
     * integration point. reference() is a true morphTo (Deposit today,
     * Subscription/Purchase later per the model's own docblock), so
     * reference_type/reference_id follow Laravel's standard morph
     * column shape. Deletion is blocked at the model level.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('reference_type');
            $table->uuid('reference_id');

            $table->string('provider')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->string('payment_method')->nullable();

            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('IDR');
            $table->string('status', 20)->default('PENDING');

            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('callback_received_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
