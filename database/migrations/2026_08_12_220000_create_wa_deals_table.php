<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaDeal — CRM Roadmap Fase 3 ("Sales Pipeline /
 * Deal"), per docs/Roadmap_CRM_WhatsApp_Konexa.docx section 2: tahapan
 * penjualan (lead -> prospek -> deal), nilai transaksi, target closing.
 *
 * Always belongs to a App\Models\WaCustomer (the Fase 0 identity), same
 * as App\Models\WaCustomerTask (Fase 2) — the roadmap table itself says
 * Fase 3 "bisa menempel aktivitas dari Fase 2", so both hang off the
 * same customer anchor rather than off a single chat/device.
 *
 * `stage` is a fixed, small string enum (see WaDeal::STAGES) rather
 * than a separate configurable-stages table — same choice this app
 * already made for wa_conversations.status (see that migration's
 * docblock/App\Models\WaConversation::STATUSES). Five stages covers
 * what the roadmap asks for without a whole extra pipeline-settings
 * CRUD; can be revisited if a company genuinely needs custom stages
 * later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_deals', function (Blueprint $table) {
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

            // Rupiah, no separate currency column — every other money
            // figure in this app (packages, deposits) is IDR-only too.
            $table->decimal('value', 15, 2)->default(0);

            // 'lead' | 'qualified' | 'negotiation' | 'won' | 'lost' —
            // see App\Models\WaDeal::STAGES.
            $table->string('stage', 20)->default('lead');

            // "Target closing" from the roadmap wording — when the team
            // expects/wants this deal decided, not when it actually was.
            $table->date('expected_close_at')->nullable();

            $table->foreignUuid('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Set the moment `stage` moves into 'won' or 'lost' — cleared
            // again if a closed deal is ever moved back to an open stage
            // (see Crm\DealController::moveStage()).
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['company_id', 'stage']);
            $table->index(['wa_customer_id']);
            $table->index(['assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_deals');
    }
};
