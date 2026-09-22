<?php

namespace App\Console\Commands;

use App\Services\PlatformAlertService;
use Illuminate\Console\Command;

/**
 * Runs every minute (see bootstrap/app.php's ->withSchedule()) and asks
 * App\Services\PlatformAlertService to check whether the rolling 15-
 * minute, across-all-companies counters (package/quota breaches + HTTP
 * 500s) have crossed their thresholds — if so, sends a single WhatsApp
 * alert to the platform owner, gated by a 30-minute cooldown. See that
 * service's docblock for the full design (CLAUDE.md checklist item #10).
 * Deliberately thin: all the actual logic lives in the service so it can
 * also be called from bootstrap/app.php's exception reporting and from
 * App\Services\PackageLimitService without depending on the console
 * kernel.
 */
class PlatformHealthAlert extends Command
{
    protected $signature = 'platform:health-alert';

    protected $description = 'Cek rolling 15-menit error rate/quota-exceeded platform, kirim WA alert ke owner kalau melewati ambang batas (CLAUDE.md checklist #10)';

    public function handle(PlatformAlertService $alerts): int
    {
        $alerts->checkAndAlert();

        return self::SUCCESS;
    }
}
