<?php

namespace App\Services\Chat;

use App\Models\Company;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Hard per-device ceiling on how many broadcast messages can actually go
 * out per minute — the anti-ban backstop that holds true regardless of
 * how many App\Models\WaMessageSchedule rows or queue workers are
 * involved, unlike App\Console\Commands\DispatchDueWaMessageSchedules'
 * own randomized stagger (which only spaces out one schedule's own
 * recipient list against itself).
 *
 * Built on Laravel's own RateLimiter (a thin, well-tested wrapper around
 * the cache's atomic increment) rather than a bespoke counter table —
 * the exact same "sliding window over a cache key" primitive Laravel
 * uses for login throttling elsewhere in this app, just keyed per device
 * instead of per IP/user.
 */
class BroadcastThrottleService
{
    /** Used when a company hasn't set companies.chat_broadcast_max_per_minute. */
    public const DEFAULT_MAX_PER_MINUTE = 10;

    /**
     * Tries to claim one send slot for this device in the current
     * rolling minute. Returns true (and consumes the slot) if under the
     * cap, false if the device is already at its limit for this window —
     * callers must not send when this returns false.
     */
    public function attempt(string $deviceId, ?string $companyId): bool
    {
        $max = $this->maxPerMinute($companyId);

        // The callback itself always returns true when allowed to run —
        // RateLimiter::attempt() returns that (i.e. true) when a slot was
        // available, or the literal `false` (never invoking the
        // callback) once $max attempts have already landed in this
        // rolling 60-second window.
        return (bool) RateLimiter::attempt($this->key($deviceId), $max, fn () => true, 60);
    }

    /** Seconds until this device's next send slot frees up, for a caller that needs to schedule a retry. */
    public function availableInSeconds(string $deviceId): int
    {
        return RateLimiter::availableIn($this->key($deviceId));
    }

    private function key(string $deviceId): string
    {
        return "wa-broadcast-throttle:{$deviceId}";
    }

    private function maxPerMinute(?string $companyId): int
    {
        if (! $companyId) {
            return self::DEFAULT_MAX_PER_MINUTE;
        }

        $company = Company::query()->find($companyId, ['chat_broadcast_max_per_minute']);

        return $company?->chat_broadcast_max_per_minute ?? self::DEFAULT_MAX_PER_MINUTE;
    }
}
