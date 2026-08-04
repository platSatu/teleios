<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\TransactionStatusHistory — a generic polymorphic
     * status-change log (entity_type/entity_id, not a real FK, since it
     * logs status transitions for Deposit today and potentially other
     * entities later).
     */
    public function up(): void
    {
        Schema::create('transaction_status_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('entity_type');
            $table->uuid('entity_id');

            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20);

            $table->foreignUuid('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_status_histories');
    }
};
