<?php

namespace App\Console\Commands;

use App\Services\Crm\CustomerAutomationService;
use Illuminate\Console\Command;

/**
 * Runs daily (see bootstrap/app.php's ->withSchedule()) — the only
 * trigger type App\Services\Crm\CustomerAutomationService can't fire
 * synchronously from a real event: 'no_contact_days' rules, since the
 * passage of time has no event to hook into. Same "flag it in bulk on a
 * schedule" shape as App\Console\Commands\EvaluateChatSlaBreaches, just
 * once a day rather than every minute — a "no contact in N days" check
 * doesn't need minute-granularity.
 */
class EvaluateCustomerAutomationRules extends Command
{
    protected $signature = 'crm:evaluate-automation-rules';

    protected $description = 'Fire time-based CRM automation rules (e.g. "no contact in N days") that are due';

    public function handle(CustomerAutomationService $automation): int
    {
        $result = $automation->evaluateTimeBasedRules();

        $this->info("Rules evaluated: {$result['rules_evaluated']}. Tasks created: {$result['tasks_created']}.");

        return self::SUCCESS;
    }
}
