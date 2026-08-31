<?php

namespace App\Services;

use App\Exceptions\PackageLimitExceededException;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\CompanyLimitUsage;
use App\Models\LimitMetric;
use App\Models\Package;
use App\Models\PackageLimit;
use App\Models\Subscription;
use App\Models\Voucher;
use App\Notifications\PackageLimitExhaustedNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The one place that knows how to answer "is this company still allowed
 * to do X?" for any App\Models\LimitMetric on any App\Models\Package —
 * built generic on purpose (see the migrations' docblocks) so a future
 * application beyond WhatsApp/Konexa can reuse this exact service by
 * just registering its own LimitMetric rows and attaching PackageLimit
 * values to its own packages, without touching this class.
 *
 * Two ways a metric is measured, per LimitMetric::metric_type:
 *   - 'consumable' — a running counter (App\Models\CompanyLimitUsage.
 *     used_value) that only resets when a new Subscription becomes
 *     active. Callers report usage via consume() after the action
 *     actually happens (e.g. a broadcast message really sent).
 *   - 'stock' — measured live against reality via a caller-supplied
 *     count callback (e.g. WaPhoneBook::count()) rather than a
 *     separately-tracked number, so it can never drift.
 */
class PackageLimitService
{
    /**
     * The company's currently valid, redeemed voucher — same "active
     * package" definition App\Http\Middleware\EnsureActivePackage uses,
     * but resolved by company_id directly (a Voucher already carries
     * one) rather than via the billing user, since usage/limits are
     * naturally a per-company concept.
     */
    public function resolveActiveVoucher(Company $company): ?Voucher
    {
        return Voucher::where('company_id', $company->id)
            ->where('status', 'active')
            ->whereNotNull('valid_from')
            ->whereNotNull('valid_until')
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->latest('valid_from')
            ->first();
    }

    public function activePackage(Company $company): ?Package
    {
        return $this->resolveActiveVoucher($company)?->package;
    }

    public function activeSubscription(Company $company): ?Subscription
    {
        return $this->resolveActiveVoucher($company)?->subscription;
    }

    /**
     * Category-SCOPED version of activePackage()/requireActivePackage()
     * above -- those two only ask "does this company have ANY active
     * package at all", which is deliberately what App\Http\Middleware     * EnsureActivePackage's Chat gate still uses today (see that
     * class's docblock: existing category_applications aren't reliably
     * tagged across every customer's package yet, so filtering the
     * long-standing Chat gate by category risks locking out already-
     * paying customers). A brand-new, category-gated feature has no
     * such legacy customers to break, so it can safely ask the sharper
     * question from day one: "does this company have an active package
     * that belongs to one of THESE categories specifically" -- e.g.
     * App\Models\JadwalReminderSetting::CHAT_CATEGORY_NAMES, so a
     * company that only ever bought a "Jadwal" package (no "Chat"/
     * "WhatsApp" category) never gets treated as WhatsApp-entitled.
     *
     * Same active-voucher shape as resolveActiveVoucher() above, plus
     * the category_application.name filter -- mirrors exactly how
     * EnsureActivePackage's own optional category argument is matched,
     * INCLUDING its backward-compat fallback (Tahap 4): a package never
     * tagged with any category (category_application_id still NULL --
     * every package that existed before category tagging was a thing)
     * counts as passing this filter too, same reasoning as
     * EnsureActivePackage's docblock. In practice this means a
     * long-time WhatsApp customer's untagged package keeps working for
     * Jadwal reminders exactly like it already does for Chat, without
     * needing anyone to go back and manually tag it first.
     */
    public function hasActiveCategoryPackage(Company $company, array $categoryNames): bool
    {
        return Voucher::where('company_id', $company->id)
            ->where('status', 'active')
            ->whereNotNull('valid_from')
            ->whereNotNull('valid_until')
            ->where('valid_from', '<=', now())
            ->where('valid_until', '>=', now())
            ->where(function ($q) use ($categoryNames) {
                $q->whereHas(
                    'package.categoryApplication',
                    fn ($qq) => $qq->whereIn('name', $categoryNames)
                )
                    ->orWhereDoesntHave('package.categoryApplication');
            })
            ->exists();
    }

    /**
     * Throws PackageLimitExceededException if the company has NO active
     * package at all right now — deliberately the opposite failure mode
     * from assertWithinLimit()/reserve()/consume() below, which all
     * fail OPEN (silently allow) when there's no active package, because
     * they're answering "is this specific metric capped by the package
     * the company has?", not "does this company have a package at all?".
     * That fail-open is correct for e.g. a metric nobody's configured a
     * PackageLimit for yet — but it's the wrong answer for "should this
     * company's WhatsApp messages keep going out after their package
     * expired", which is exactly what this method is for.
     *
     * Why this needs to exist as its own check rather than just relying
     * on assertWithinLimit('broadcast_send', ...): App\Http\Middleware\
     * EnsureActivePackage already blocks an expired company from the
     * dashboard UI, but it's HTTP middleware — it never runs for
     * App\Console\Commands\DispatchDueWaMessageSchedules (a scheduled
     * artisan command) or the queue jobs it dispatches
     * (App\Jobs\SendScheduledWaMessage, SendAutoReplyMessage,
     * SendAiBotReply). A schedule created while the package was active
     * keeps being picked up by cron and sent forever afterwards unless
     * something inside those jobs themselves checks this. Call this
     * before every send in those jobs, in ADDITION to (not instead of)
     * the existing reserve()/BroadcastThrottleService checks — this
     * answers "are they still a customer at all", those answer "have
     * they used up what they're entitled to this period".
     */
    public function requireActivePackage(Company $company): void
    {
        if ($this->activePackage($company) === null) {
            throw new PackageLimitExceededException(
                'Masa aktif package perusahaan ini sudah habis. Redeem voucher atau beli package baru untuk melanjutkan pengiriman WhatsApp.',
                'active_package'
            );
        }
    }

    /**
     * The catalog row for a metric key, preferring one scoped to the
     * given product/application (if any) and falling back to a global
     * (category_application_id = null) row with the same key — this is
     * what lets a metric key like "contact_count" be reused as-is by a
     * future application instead of every product needing its own copy.
     */
    public function metricByKey(string $key, ?string $categoryApplicationId = null): ?LimitMetric
    {
        $query = LimitMetric::where('key', $key)->where('status', 'active');

        if ($categoryApplicationId) {
            $scoped = (clone $query)->where('category_application_id', $categoryApplicationId)->first();

            if ($scoped) {
                return $scoped;
            }
        }

        return $query->whereNull('category_application_id')->first();
    }

    /**
     * The max_value the company's active package sets for this metric,
     * or null if there's no active package, no LimitMetric registered
     * for this key, or the package simply doesn't cap this metric —
     * every one of those cases means "unlimited", never "blocked".
     */
    public function limitFor(Company $company, string $metricKey): ?PackageLimit
    {
        $package = $this->activePackage($company);

        if (! $package) {
            return null;
        }

        $metric = $this->metricByKey($metricKey, $package->category_application_id);

        if (! $metric) {
            return null;
        }

        return PackageLimit::where('package_id', $package->id)
            ->where('limit_metric_id', $metric->id)
            ->first();
    }

    /**
     * The counter row for a consumable metric, scoped to the company's
     * currently active subscription — created on first use. Wrapped in a
     * locked transaction (same lockForUpdate() convention App\Helpers\
     * CrudAdmin uses) so two near-simultaneous actions can't both read
     * "1 remaining" and both go through.
     */
    protected function usageRow(Company $company, LimitMetric $metric, ?BranchOffice $branch, ?Subscription $subscription): CompanyLimitUsage
    {
        return DB::transaction(fn () => $this->lockOrCreateUsage($company, $metric, $branch, $subscription));
    }

    /**
     * Fetches (with a row lock) or creates the counter row for one
     * (company, branch, metric, subscription) combination — MUST be
     * called from inside an existing DB::transaction(), never on its
     * own. `lockForUpdate()` only locks rows that already exist, so it
     * can't by itself stop two simultaneous callers from both trying to
     * create the FIRST row for a brand new combination; the retry below
     * catches that race via the `usage_key` unique constraint (see the
     * company_limit_usages migration's docblock) instead of letting a
     * duplicate slip through or the whole operation blow up.
     */
    protected function lockOrCreateUsage(Company $company, LimitMetric $metric, ?BranchOffice $branch, ?Subscription $subscription): CompanyLimitUsage
    {
        $find = fn () => CompanyLimitUsage::where('company_id', $company->id)
            ->where('branch_office_id', $branch?->id)
            ->where('limit_metric_id', $metric->id)
            ->where('subscription_id', $subscription?->id)
            ->lockForUpdate()
            ->first();

        $row = $find();

        if ($row) {
            return $row;
        }

        try {
            return CompanyLimitUsage::create([
                'company_id' => $company->id,
                'branch_office_id' => $branch?->id,
                'limit_metric_id' => $metric->id,
                'subscription_id' => $subscription?->id,
                'used_value' => 0,
                'period_start' => $subscription?->start_date,
                'period_end' => $subscription?->end_date,
            ]);
        } catch (QueryException $e) {
            // Lost the race for the first row on this combination —
            // someone else's transaction created (and committed) it a
            // moment ago. Fetch and lock theirs instead of failing.
            $row = $find();

            if ($row) {
                return $row;
            }

            throw $e;
        }
    }

    /**
     * How many of this metric the company can still use — null means
     * unlimited (no active package, or the package doesn't cap this
     * metric). For a 'stock' metric, $liveCountResolver must be supplied
     * (returns the current real count); ignored for 'consumable'.
     */
    public function remaining(Company $company, string $metricKey, ?BranchOffice $branch = null, ?callable $liveCountResolver = null): ?int
    {
        $packageLimit = $this->limitFor($company, $metricKey);

        if (! $packageLimit) {
            return null;
        }

        $metric = $packageLimit->limitMetric;

        if ($metric->isStock()) {
            $used = $liveCountResolver ? (int) $liveCountResolver() : 0;

            return max(0, $packageLimit->max_value - $used);
        }

        $usage = $this->usageRow($company, $metric, $branch, $this->activeSubscription($company));

        return max(0, $packageLimit->max_value - $usage->used_value);
    }

    /**
     * Throws App\Exceptions\PackageLimitExceededException if performing
     * this action ($amount units of $metricKey) would exceed the
     * company's active package limit. Does nothing (silently allows) if
     * there's no active package or no limit registered — this is a
     * deliberate fail-open so a company simply never sees a block until
     * a superadmin actually configures a limit for their package.
     */
    public function assertWithinLimit(Company $company, string $metricKey, int $amount = 1, ?BranchOffice $branch = null, ?callable $liveCountResolver = null): void
    {
        $packageLimit = $this->limitFor($company, $metricKey);

        if (! $packageLimit) {
            return;
        }

        $metric = $packageLimit->limitMetric;

        if ($metric->isStock()) {
            $used = $liveCountResolver ? (int) $liveCountResolver() : 0;

            if ($used + $amount > $packageLimit->max_value) {
                throw new PackageLimitExceededException(
                    "Batas {$metric->name} paket Anda sudah tercapai ({$packageLimit->max_value} {$metric->unit}). Hapus data lama atau upgrade paket untuk menambah kapasitas.",
                    $metricKey
                );
            }

            return;
        }

        $subscription = $this->activeSubscription($company);
        $usage = $this->usageRow($company, $metric, $branch, $subscription);

        if ($usage->used_value + $amount > $packageLimit->max_value) {
            $this->notifyExhausted($company, $metric, $usage, $packageLimit->max_value);

            throw new PackageLimitExceededException(
                "Kuota {$metric->name} paket Anda untuk periode ini sudah habis ({$usage->used_value}/{$packageLimit->max_value} {$metric->unit}). Beli/upgrade paket untuk melanjutkan.",
                $metricKey
            );
        }
    }

    /**
     * Records that $amount units of a 'consumable' metric were actually
     * used — call this AFTER the action succeeds (e.g. a broadcast
     * message really went out), never before, so a failed action never
     * eats into the company's quota. No-op for 'stock' metrics (their
     * usage is always measured live, never stored).
     */
    public function consume(Company $company, string $metricKey, int $amount = 1, ?BranchOffice $branch = null): void
    {
        $package = $this->activePackage($company);

        if (! $package) {
            return;
        }

        $metric = $this->metricByKey($metricKey, $package->category_application_id);

        if (! $metric || ! $metric->isConsumable()) {
            return;
        }

        $subscription = $this->activeSubscription($company);

        DB::transaction(function () use ($company, $metric, $branch, $subscription, $amount) {
            $usage = $this->lockOrCreateUsage($company, $metric, $branch, $subscription);
            $usage->increment('used_value', $amount);
        });
    }

    /**
     * Atomically checks AND consumes $amount units of a 'consumable'
     * metric in one locked transaction — unlike a separate
     * assertWithinLimit() + consume() pair (still fine for simple
     * synchronous actions like a single form submit), this closes the
     * check-then-consume race a highly concurrent caller would otherwise
     * hit — e.g. many App\Jobs\SendScheduledWaMessage jobs for the same
     * company running across several queue workers at once, all reading
     * "still room" before any of them writes back. `lockForUpdate()`
     * inside the same transaction as the increment means a second
     * concurrent caller blocks until the first one's write commits,
     * instead of both reading a stale count.
     *
     * Call this immediately before the action that actually spends the
     * quota (e.g. right before the network send), and call release() if
     * that action then fails, so a retried/failed attempt never
     * permanently burns quota it never actually used.
     *
     * Throws PackageLimitExceededException — without reserving anything
     * — if there's no room left. Fails open (reserves nothing, no error)
     * if there's no active package, no LimitMetric for this key, no
     * PackageLimit configured, or the metric isn't 'consumable' (a
     * 'stock' metric has no counter to reserve against — use
     * assertWithinLimit() with a live-count resolver for those instead).
     */
    public function reserve(Company $company, string $metricKey, int $amount = 1, ?BranchOffice $branch = null): void
    {
        $package = $this->activePackage($company);

        if (! $package) {
            return;
        }

        $metric = $this->metricByKey($metricKey, $package->category_application_id);

        if (! $metric || ! $metric->isConsumable()) {
            return;
        }

        $packageLimit = PackageLimit::where('package_id', $package->id)
            ->where('limit_metric_id', $metric->id)
            ->first();

        if (! $packageLimit) {
            return;
        }

        $subscription = $this->activeSubscription($company);

        DB::transaction(function () use ($company, $metric, $branch, $subscription, $amount, $packageLimit) {
            $usage = $this->lockOrCreateUsage($company, $metric, $branch, $subscription);

            if ($usage->used_value + $amount > $packageLimit->max_value) {
                $this->notifyExhausted($company, $metric, $usage, $packageLimit->max_value);

                throw new PackageLimitExceededException(
                    "Kuota {$metric->name} paket Anda untuk periode ini sudah habis ({$usage->used_value}/{$packageLimit->max_value} {$metric->unit}). Beli/upgrade paket untuk melanjutkan.",
                    $metric->key
                );
            }

            $usage->increment('used_value', $amount);
        });
    }

    /**
     * Gives back a reservation made by reserve() — call this when the
     * action it was guarding ultimately failed (e.g. the WhatsApp send
     * threw and will be retried, or failed for good), so quota is only
     * ever permanently spent on something that actually happened. No-op
     * for the same fail-open cases as reserve(); floors at 0 rather than
     * going negative.
     */
    public function release(Company $company, string $metricKey, int $amount = 1, ?BranchOffice $branch = null): void
    {
        $package = $this->activePackage($company);

        if (! $package) {
            return;
        }

        $metric = $this->metricByKey($metricKey, $package->category_application_id);

        if (! $metric || ! $metric->isConsumable()) {
            return;
        }

        $subscription = $this->activeSubscription($company);

        DB::transaction(function () use ($company, $metric, $branch, $subscription, $amount) {
            $usage = CompanyLimitUsage::where('company_id', $company->id)
                ->where('branch_office_id', $branch?->id)
                ->where('limit_metric_id', $metric->id)
                ->where('subscription_id', $subscription?->id)
                ->lockForUpdate()
                ->first();

            if ($usage) {
                $usage->update(['used_value' => max(0, $usage->used_value - $amount)]);
            }
        });
    }

    /**
     * Emails the company owner once per exhaustion (throttled via
     * `notified_at` — only sent again if the row's notified_at is still
     * null, i.e. not yet notified for this subscription period), rather
     * than on every single blocked attempt.
     */
    public function notifyExhausted(Company $company, LimitMetric $metric, CompanyLimitUsage $usage, int $maxValue): void
    {
        if ($usage->notified_at !== null) {
            return;
        }

        $owner = $company->user;

        if ($owner) {
            $owner->notify(new PackageLimitExhaustedNotification($metric->name, $metric->unit, $maxValue));
        }

        $usage->forceFill(['notified_at' => now()])->save();
    }

    /**
     * Purchased vs used vs remaining for every metric the company's
     * active package caps — powers the company-facing usage report page
     * (dashboard.package.usage). $liveCountResolvers is an optional
     * [metric_key => callable] map the caller supplies for 'stock'
     * metrics it knows how to count live (e.g. 'contact_count' =>
     * fn() => WaPhoneBook::where(...)->count()); a stock metric with no
     * resolver supplied shows as "unknown" (null) rather than a
     * misleading 0.
     *
     * @param  array<string, callable>  $liveCountResolvers
     * @return array<int, array{metric: LimitMetric, max_value: int, used: ?int, remaining: ?int, period_start: ?\Illuminate\Support\Carbon, period_end: ?\Illuminate\Support\Carbon}>
     */
    public function usageReport(Company $company, ?BranchOffice $branch = null, array $liveCountResolvers = []): array
    {
        $package = $this->activePackage($company);

        if (! $package) {
            return [];
        }

        $subscription = $this->activeSubscription($company);
        $limits = PackageLimit::with('limitMetric')->where('package_id', $package->id)->get();

        return $limits->map(function (PackageLimit $packageLimit) use ($company, $branch, $subscription, $liveCountResolvers) {
            $metric = $packageLimit->limitMetric;

            if ($metric->isStock()) {
                $resolver = $liveCountResolvers[$metric->key] ?? null;
                $used = $resolver ? $resolver() : null;
                $used = $used !== null ? (int) $used : null;

                return [
                    'metric' => $metric,
                    'max_value' => $packageLimit->max_value,
                    'used' => $used,
                    'remaining' => $used !== null ? max(0, $packageLimit->max_value - $used) : null,
                    'period_start' => null,
                    'period_end' => null,
                ];
            }

            $usage = $this->usageRow($company, $metric, $branch, $subscription);

            return [
                'metric' => $metric,
                'max_value' => $packageLimit->max_value,
                'used' => $usage->used_value,
                'remaining' => max(0, $packageLimit->max_value - $usage->used_value),
                'period_start' => $usage->period_start,
                'period_end' => $usage->period_end,
            ];
        })->all();
    }
}
