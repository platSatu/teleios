<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaCustomerTask — CRM Roadmap Fase 2 ("Task &
 * Follow-up"), per docs/Roadmap_CRM_WhatsApp_Konexa.docx section 2.
 *
 * Different from App\Models\WaChatNote (freeform, passive, per-chat
 * documentation with no due date or owner) in every way that matters
 * for a follow-up workflow: a task always belongs to a
 * App\Models\WaCustomer (the Fase 0 identity, not a single chat, so it
 * survives across every device/number that customer ever writes in
 * from), can be given a due date and an assignee, and has a real
 * done/not-done state a team can build a daily queue around — see
 * App\Http\Controllers\Crm\CustomerTaskController's docblock for the
 * "Tugas & Follow-up" page this powers.
 *
 * branch_office_id is denormalized from the owning WaCustomer at
 * creation time (same pattern App\Models\WaContact/WaPhoneBook already
 * use) purely so a branch-locked team member's task queue can be
 * filtered with a plain WHERE instead of a join back to wa_customers on
 * every request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_customer_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignUuid('branch_office_id')
                ->nullable()
                ->constrained('branch_offices')
                ->nullOnDelete();

            $table->foreignUuid('wa_customer_id')
                ->constrained('wa_customers')
                ->cascadeOnDelete();

            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->timestamp('due_at')->nullable();

            $table->foreignUuid('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // 'pending' | 'done' — see WaCustomerTask::STATUSES. No
            // separate "cancelled" state: an unwanted task is just
            // deleted, same as a Buku Telepon entry.
            $table->string('status', 20)->default('pending');

            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'status', 'due_at']);
            $table->index(['assigned_to', 'status']);
            $table->index(['wa_customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customer_tasks');
    }
};
