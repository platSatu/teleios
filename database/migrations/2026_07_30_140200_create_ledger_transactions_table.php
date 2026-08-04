<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\LedgerTransaction — the header row for a group
     * of double-entry ledger_entries. reference_type/reference_id are
     * plain indexed string columns (not a real Laravel morphTo FK):
     * e.g. Deposit sets reference_type = Deposit::class so it can look
     * up "the ledger transaction this deposit produced" via morphOne.
     */
    public function up(): void
    {
        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('transaction_number')->unique();
            $table->string('transaction_type', 30); // DEPOSIT | ADJUSTMENT | ...

            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();

            $table->string('status', 20)->default('PENDING');
            $table->text('description')->nullable();

            // nullOnDelete: who initiated it is informational — losing
            // the user shouldn't block or cascade-delete ledger history.
            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_transactions');
    }
};
