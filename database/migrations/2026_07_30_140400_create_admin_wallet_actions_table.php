<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\AdminWalletAction — records a superadmin manually
     * crediting/debiting a user's wallet (point 1.3 of the deposit/wallet
     * request: "dapat mengurangi saldo dan menambah saldo lengkap juga
     * dengan history nya"). Deletion is blocked at the model level, same
     * spirit as audit_logs — this IS the audit trail for admin-initiated
     * balance changes, on top of the underlying ledger_entries.
     */
    public function up(): void
    {
        Schema::create('admin_wallet_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('wallet_id')
                ->constrained('wallets')
                ->restrictOnDelete();

            $table->foreignUuid('admin_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('action', 30); // CREDIT | DEBIT
            $table->decimal('amount', 15, 2);
            $table->string('direction', 10); // CREDIT | DEBIT

            $table->text('reason')->nullable();

            // Only one role (SUPERADMIN) exists in this app today, so
            // there's no separate second-approver workflow — actions are
            // self-approved in the same request (status defaults to
            // 'approved', approved_by/approved_at set immediately). The
            // columns stay nullable/status-driven so a real two-person
            // approval step can be layered in later without a migration.
            $table->string('status', 20)->default('approved');

            $table->foreignUuid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_wallet_actions');
    }
};
