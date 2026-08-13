<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaCustomerAutomationRule — CRM Roadmap Fase 4's
 * "automasi follow-up berbasis trigger". A rule is "when X happens to a
 * customer, do Y" — see App\Services\Crm\CustomerAutomationService (the
 * one place every trigger is evaluated and every action executed) for
 * the full mechanics.
 *
 * `action_type` is a single fixed value ('create_task') for now rather
 * than a real enum of choices — creating a App\Models\WaCustomerTask
 * (Fase 2) is the one action that's genuinely useful across all three
 * trigger types this ships with, and keeps the rule builder UI from
 * needing per-action-type conditional fields on top of the per-trigger-
 * type ones it already has. More actions can be added later without a
 * schema change (action_config is already JSON).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_customer_automation_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->string('name', 150);

            // 'deal_stage_changed' | 'tag_added' | 'no_contact_days' —
            // see App\Models\WaCustomerAutomationRule::TRIGGER_TYPES.
            $table->string('trigger_type', 30);

            // Shape depends on trigger_type:
            //   deal_stage_changed -> {"stage": "won"}
            //   tag_added          -> {"tag_id": "<uuid>"}
            //   no_contact_days    -> {"days": 7}
            $table->json('trigger_config');

            $table->string('action_type', 30)->default('create_task');

            // {"title": "...", "due_in_days": 2, "assigned_to": "<uuid>|null"}
            $table->json('action_config');

            $table->boolean('is_active')->default(true);

            $table->foreignUuid('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Explicit short name — MySQL's default auto-generated name
            // for this composite ('wa_customer_automation_rules_company_
            // id_trigger_type_is_active_index') is 71 characters, over
            // MySQL's 64-character identifier limit.
            $table->index(['company_id', 'trigger_type', 'is_active'], 'wa_cust_automation_rules_company_trigger_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customer_automation_rules');
    }
};
