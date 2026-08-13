<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs App\Models\WaCustomerAutomationLog — one row per time a
 * App\Models\WaCustomerAutomationRule actually fired for a specific
 * App\Models\WaCustomer. Two jobs at once:
 *
 *  1. Cooldown guard for time-based rules (trigger_type
 *     'no_contact_days') — App\Services\Crm\CustomerAutomationService::
 *     evaluateTimeBasedRules() runs daily and would otherwise create a
 *     new follow-up task for the same still-silent customer every
 *     single day; it checks here first and skips re-firing within the
 *     rule's own cooldown window.
 *  2. A visible audit trail — "why did this task get created" is
 *     answerable by looking up which rule/customer pair logged a fire
 *     around that time, without digging through wa_customer_tasks'
 *     freeform title text.
 *
 * Event-based rules (deal_stage_changed, tag_added) also log here for
 * the same audit-trail reason, even though they don't need the cooldown
 * check (an event only fires once per actual stage change/tag attach).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_customer_automation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Plain uuid() + explicit foreign() (rather than
            // foreignUuid()->constrained()) so the constraint name can be
            // set short by hand — the auto-generated name for this
            // column ('wa_customer_automation_logs_wa_customer_
            // automation_rule_id_foreign') is 66 characters, over
            // MySQL's 64-character identifier limit.
            $table->uuid('wa_customer_automation_rule_id');
            $table->foreign('wa_customer_automation_rule_id', 'wa_cust_automation_logs_rule_id_foreign')
                ->references('id')->on('wa_customer_automation_rules')
                ->cascadeOnDelete();

            $table->foreignUuid('wa_customer_id')
                ->constrained('wa_customers')
                ->cascadeOnDelete();

            $table->timestamp('fired_at');

            $table->index(['wa_customer_automation_rule_id', 'wa_customer_id', 'fired_at'], 'wa_cust_automation_logs_rule_customer_fired_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_customer_automation_logs');
    }
};
