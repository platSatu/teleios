<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backs App\Models\VoucherHistory — an audit trail specifically for
     * voucher create/update/delete actions (Superadmin\VoucherController
     * writes to this after this migration lands). voucher_id is
     * intentionally a plain uuid column with no foreign key, same
     * reasoning as audit_logs.entity_id: a history row must survive
     * even after the voucher it describes is deleted, otherwise the
     * "this voucher was deleted" entry would itself be wiped out by
     * the cascade.
     */
    public function up(): void
    {
        Schema::create('voucher_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('voucher_id')->index();

            $table->foreignUuid('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('action_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 20); // CREATE | UPDATE | DELETE
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_histories');
    }
};
