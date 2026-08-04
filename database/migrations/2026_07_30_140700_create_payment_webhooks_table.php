<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\PaymentWebhook — raw inbound webhook storage for
     * the future payment gateway (signature verification + idempotent
     * processing). Deletion is blocked at the model level, same as
     * payment_transactions.
     */
    public function up(): void
    {
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('provider');
            $table->string('event_type')->nullable();
            $table->string('signature')->nullable();
            $table->json('payload')->nullable();

            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('processing_error')->nullable();

            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();

            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
    }
};
