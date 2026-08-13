<?php

namespace App\Services\Crm;

use App\Models\WaCustomer;
use App\Models\WaCustomerAutomationLog;
use App\Models\WaCustomerAutomationRule;
use App\Models\WaCustomerTag;
use App\Models\WaCustomerTask;
use App\Models\WaDeal;

/**
 * CRM Roadmap Fase 4 — the one place every App\Models\
 * WaCustomerAutomationRule is evaluated and every action executed, same
 * "single owner" convention App\Services\Chat\ConversationService set
 * for wa_conversations and App\Services\Crm\CustomerIdentityService set
 * for WaCustomer resolution.
 *
 * Two of the three trigger types fire synchronously from the action
 * that causes them (see fireDealStageChanged()/fireTagAdded(), called
 * from App\Http\Controllers\Crm\DealController::moveStage() and
 * App\Http\Controllers\Crm\CustomerTagController::attach()
 * respectively) — a real event happened, so there's nothing to poll
 * for. The third (no_contact_days) has no event to hook — the passage
 * of time isn't one — so evaluateTimeBasedRules() runs instead from the
 * scheduled `crm:evaluate-automation-rules` command (see
 * App\Console\Commands\EvaluateCustomerAutomationRules and
 * bootstrap/app.php's ->withSchedule()), the same pattern
 * App\Console\Commands\EvaluateChatSlaBreaches already uses for
 * time-based flagging.
 *
 * Every fire — event-based or time-based — is logged to
 * App\Models\WaCustomerAutomationLog, which the time-based path also
 * reads back as a cooldown guard (see evaluateTimeBasedRules()) so a
 * still-silent customer doesn't get a fresh follow-up task created
 * every single day the command runs.
 */
class CustomerAutomationService
{
    /**
     * Called after a App\Models\WaDeal's stage is changed (see
     * App\Http\Controllers\Crm\DealController::moveStage()) — fires
     * every active 'deal_stage_changed' rule whose configured stage
     * matches the deal's new stage.
     */
    public function fireDealStageChanged(WaDeal $deal): void
    {
        if (! $deal->wa_customer_id) {
            return;
        }

        $rules = WaCustomerAutomationRule::where('company_id', $deal->company_id)
            ->where('trigger_type', WaCustomerAutomationRule::TRIGGER_DEAL_STAGE_CHANGED)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            if (($rule->trigger_config['stage'] ?? null) !== $deal->stage) {
                continue;
            }

            $customer = $deal->customer ?? WaCustomer::find($deal->wa_customer_id);

            if ($customer) {
                $this->createTaskFromRule($rule, $customer);
            }
        }
    }

    /**
     * Called after a tag is attached to a customer (see
     * App\Http\Controllers\Crm\CustomerTagController::attach()) — fires
     * every active 'tag_added' rule configured for that exact tag.
     */
    public function fireTagAdded(WaCustomer $customer, WaCustomerTag $tag): void
    {
        $rules = WaCustomerAutomationRule::where('company_id', $customer->company_id)
            ->where('trigger_type', WaCustomerAutomationRule::TRIGGER_TAG_ADDED)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            if (($rule->trigger_config['tag_id'] ?? null) !== $tag->id) {
                continue;
            }

            $this->createTaskFromRule($rule, $customer);
        }
    }

    /**
     * Run by the daily `crm:evaluate-automation-rules` command — every
     * active 'no_contact_days' rule, applied across every company at
     * once (this is a global sweep, not scoped to one request). A
     * customer already logged as fired for a given rule within that
     * rule's own `days` window is skipped, so a customer who stays
     * silent doesn't get a new task every day — only once per window.
     *
     * @return array{rules_evaluated: int, tasks_created: int}
     */
    public function evaluateTimeBasedRules(): array
    {
        $rules = WaCustomerAutomationRule::where('trigger_type', WaCustomerAutomationRule::TRIGGER_NO_CONTACT_DAYS)
            ->where('is_active', true)
            ->get();

        $tasksCreated = 0;

        foreach ($rules as $rule) {
            $days = (int) ($rule->trigger_config['days'] ?? 0);

            if ($days <= 0) {
                continue;
            }

            $cutoff = now()->subDays($days);

            $alreadyFiredCustomerIds = WaCustomerAutomationLog::where('wa_customer_automation_rule_id', $rule->id)
                ->where('fired_at', '>=', $cutoff)
                ->pluck('wa_customer_id');

            $customers = WaCustomer::where('company_id', $rule->company_id)
                ->where(function ($q) use ($cutoff) {
                    $q->whereNull('last_contacted_at')
                        ->orWhere('last_contacted_at', '<=', $cutoff);
                })
                ->whereNotIn('id', $alreadyFiredCustomerIds)
                ->get();

            foreach ($customers as $customer) {
                $this->createTaskFromRule($rule, $customer);
                $tasksCreated++;
            }
        }

        return [
            'rules_evaluated' => $rules->count(),
            'tasks_created' => $tasksCreated,
        ];
    }

    /**
     * The one action every rule currently supports (action_type is
     * always 'create_task' for now — see the wa_customer_automation_
     * rules migration's docblock). Always logs the fire, even though
     * only the time-based caller actually reads logs back.
     */
    private function createTaskFromRule(WaCustomerAutomationRule $rule, WaCustomer $customer): void
    {
        $config = $rule->action_config ?? [];
        $dueInDays = (int) ($config['due_in_days'] ?? 1);

        WaCustomerTask::create([
            'company_id' => $customer->company_id,
            'branch_office_id' => $customer->branch_office_id,
            'wa_customer_id' => $customer->id,
            'title' => $config['title'] ?? "Follow-up otomatis: {$rule->name}",
            'due_at' => now()->addDays(max($dueInDays, 0)),
            'assigned_to' => $config['assigned_to'] ?? null,
            'status' => WaCustomerTask::STATUS_PENDING,
        ]);

        WaCustomerAutomationLog::create([
            'wa_customer_automation_rule_id' => $rule->id,
            'wa_customer_id' => $customer->id,
            'fired_at' => now(),
        ]);
    }
}
