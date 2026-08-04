<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * App\Models\Subscription already existed in the codebase (fillable,
     * casts, delete guard for ACTIVE subscriptions) but had no matching
     * migration — the table never actually existed. This creates it to
     * match that model exactly, so the package checkout flow (Dashboard\
     * PackageCheckoutController) has somewhere to write purchase records.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Nullable + nullOnDelete: a purchase record should outlive
            // the package it was for, in case a superadmin removes the
            // package later — otherwise a user's own purchase history
            // would vanish.
            $table->foreignUuid('package_id')
                ->nullable()
                ->constrained('packages')
                ->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('IDR');

            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();

            // ACTIVE | EXPIRED | CANCELLED — App\Models\Subscription's
            // deleting() guard refuses to delete a row while this is
            // ACTIVE.
            $table->string('status', 20)->default('ACTIVE');

            $table->boolean('auto_renew')->default(false);

            $table->foreignUuid('payment_transaction_id')
                ->nullable()
                ->constrained('payment_transactions')
                ->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
