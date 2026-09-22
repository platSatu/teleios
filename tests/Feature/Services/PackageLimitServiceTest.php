<?php

/**
 * CLAUDE.md checklist item #11 — automated coverage for
 * App\Services\PackageLimitService, the core "does this company still
 * have quota / an active package" logic behind checklist item #3
 * (centralized WA-send protection). This is the one area of the app that
 * directly gates whether a paying customer's messages go out, so it's
 * the highest-value place to have tests catch a regression before a
 * human notices it manually.
 *
 * No factories exist yet for Company/Package/Voucher/etc (confirmed via
 * `ls database/factories/` before writing this), so fixtures are built
 * directly via Model::create() with exactly the columns each model
 * requires (verified against each model's migration + boot() hooks) —
 * see helper functions at the bottom of this file.
 */

use App\Exceptions\PackageLimitExceededException;
use App\Models\CategoryApplication;
use App\Models\Company;
use App\Models\CompanyLimitUsage;
use App\Models\LimitMetric;
use App\Models\Package;
use App\Models\PackageLimit;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use App\Services\PackageLimitService;

// =========================================================
// requireActivePackage() — fail CLOSED: no active package at all
// =========================================================

test('requireActivePackage throws when company has no active voucher/package', function () {
    $company = makeCompany();

    expect(fn () => app(PackageLimitService::class)->requireActivePackage($company))
        ->toThrow(PackageLimitExceededException::class);
});

test('requireActivePackage does not throw when company has an active voucher', function () {
    $company = makeCompany();
    makeActiveVoucher($company, makePackage());

    app(PackageLimitService::class)->requireActivePackage($company);

    // No exception thrown = pass. Pest needs an assertion to not be
    // "risky" — expect(true)->toBeTrue() documents the intent explicitly.
    expect(true)->toBeTrue();
});

test('requireActivePackage throws when the voucher exists but is expired', function () {
    $company = makeCompany();
    $package = makePackage();

    Voucher::create([
        'user_id' => $company->user_id,
        'company_id' => $company->id,
        'package_id' => $package->id,
        'kode_voucher' => Voucher::generateUniqueCode(),
        'status' => 'active',
        'valid_from' => now()->subDays(60),
        'valid_until' => now()->subDays(30), // expired 30 days ago
    ]);

    expect(fn () => app(PackageLimitService::class)->requireActivePackage($company))
        ->toThrow(PackageLimitExceededException::class);
});

// =========================================================
// assertWithinLimit() — consumable metric — fails OPEN when unconfigured,
// fails CLOSED (throws) only once actually over the configured limit
// =========================================================

test('assertWithinLimit allows the action when the company has no active package at all (fail open)', function () {
    $company = makeCompany();

    app(PackageLimitService::class)->assertWithinLimit($company, 'broadcast_send');

    expect(true)->toBeTrue();
});

test('assertWithinLimit allows the action when the metric has no PackageLimit configured for this package (fail open)', function () {
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    // 'broadcast_send' LimitMetric exists globally but NO PackageLimit
    // row ties it to this package — checklist item #7's exact scenario.
    makeLimitMetric('broadcast_send');

    app(PackageLimitService::class)->assertWithinLimit($company, 'broadcast_send');

    expect(true)->toBeTrue();
});

test('assertWithinLimit throws once usage would exceed the configured max_value', function () {
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 5]);

    CompanyLimitUsage::create([
        'company_id' => $company->id,
        'limit_metric_id' => $metric->id,
        'used_value' => 5, // already at the cap
    ]);

    expect(fn () => app(PackageLimitService::class)->assertWithinLimit($company, 'broadcast_send'))
        ->toThrow(PackageLimitExceededException::class);
});

test('assertWithinLimit allows the action while still under the configured max_value', function () {
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 5]);

    CompanyLimitUsage::create([
        'company_id' => $company->id,
        'limit_metric_id' => $metric->id,
        'used_value' => 3,
    ]);

    app(PackageLimitService::class)->assertWithinLimit($company, 'broadcast_send');

    expect(true)->toBeTrue();
});

// =========================================================
// assertWithinLimit() — stock metric — measured live, never stored
// =========================================================

test('assertWithinLimit (stock metric) throws when the live count would exceed max_value', function () {
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('contact_count', LimitMetric::TYPE_STOCK);
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 100]);

    expect(fn () => app(PackageLimitService::class)->assertWithinLimit(
        $company,
        'contact_count',
        amount: 1,
        liveCountResolver: fn () => 100 // already at the cap
    ))->toThrow(PackageLimitExceededException::class);
});

test('assertWithinLimit (stock metric) allows the action under the live-counted max_value', function () {
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('contact_count', LimitMetric::TYPE_STOCK);
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 100]);

    app(PackageLimitService::class)->assertWithinLimit(
        $company,
        'contact_count',
        amount: 1,
        liveCountResolver: fn () => 50
    );

    expect(true)->toBeTrue();
});

// =========================================================
// reserve() / release() — atomic check-and-consume, used by
// InboxService's guardPackageLimit()/releasePackageLimit() (checklist #3)
// =========================================================

test('reserve increments used_value when there is room', function () {
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 10]);

    app(PackageLimitService::class)->reserve($company, 'broadcast_send');

    $usage = CompanyLimitUsage::where('company_id', $company->id)->where('limit_metric_id', $metric->id)->first();
    expect($usage->used_value)->toBe(1);
});

test('reserve throws WITHOUT incrementing used_value once the limit is already reached', function () {
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 3]);
    CompanyLimitUsage::create(['company_id' => $company->id, 'limit_metric_id' => $metric->id, 'used_value' => 3]);

    expect(fn () => app(PackageLimitService::class)->reserve($company, 'broadcast_send'))
        ->toThrow(PackageLimitExceededException::class);

    $usage = CompanyLimitUsage::where('company_id', $company->id)->where('limit_metric_id', $metric->id)->first();
    // This is the exact bug InboxService::send()'s try/catch around
    // guardPackageLimit()/reserve() is guarding against: a failed
    // send() must never have silently burned a unit of quota.
    expect($usage->used_value)->toBe(3);
});

test('release gives back a unit reserve() consumed, so a failed send does not permanently burn quota', function () {
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 10]);

    $service = app(PackageLimitService::class);
    $service->reserve($company, 'broadcast_send');
    $service->release($company, 'broadcast_send');

    $usage = CompanyLimitUsage::where('company_id', $company->id)->where('limit_metric_id', $metric->id)->first();
    expect($usage->used_value)->toBe(0);
});

test('release never goes below 0 even if called more times than reserve()', function () {
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 10]);
    CompanyLimitUsage::create(['company_id' => $company->id, 'limit_metric_id' => $metric->id, 'used_value' => 0]);

    app(PackageLimitService::class)->release($company, 'broadcast_send');

    $usage = CompanyLimitUsage::where('company_id', $company->id)->where('limit_metric_id', $metric->id)->first();
    expect($usage->used_value)->toBe(0);
});

// =========================================================
// remaining() — read-only check powering the usage report page
// =========================================================

test('remaining returns null (unlimited) when there is no active package', function () {
    $company = makeCompany();

    expect(app(PackageLimitService::class)->remaining($company, 'broadcast_send'))->toBeNull();
});

test('remaining returns max_value minus used_value for a consumable metric', function () {
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 10]);
    CompanyLimitUsage::create(['company_id' => $company->id, 'limit_metric_id' => $metric->id, 'used_value' => 4]);

    expect(app(PackageLimitService::class)->remaining($company, 'broadcast_send'))->toBe(6);
});

// =========================================================
// Test fixture helpers — no factories exist for these models yet
// =========================================================

// Guarded with function_exists() because tests/Feature/Services/
// InboxServiceGuardTest.php (checklist #11's second test file) shares
// these exact same fixture helpers — Pest loads every test file into
// one shared global scope, so an unguarded redeclaration here would
// fatal-error with "Cannot redeclare" depending on file load order.

if (! function_exists('makeCompany')) {
    function makeCompany(): Company
    {
        $user = User::factory()->create();

        return Company::create([
            'user_id' => $user->id,
            'name' => 'Test Company '.uniqid(),
            'slug' => 'test-company-'.uniqid(),
        ]);
    }
}

if (! function_exists('makePackage')) {
    function makePackage(): Package
    {
        $category = CategoryApplication::create(['name' => 'WhatsApp Blast '.uniqid()]);

        return Package::create([
            'category_application_id' => $category->id,
            'name' => 'Test Package',
            'duration' => 30,
            'price' => 100000,
        ]);
    }
}

if (! function_exists('makeActiveVoucher')) {
    function makeActiveVoucher(Company $company, Package $package): Voucher
    {
        $subscription = Subscription::create([
            'user_id' => $company->user_id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDays(29),
        ]);

        return Voucher::create([
            'user_id' => $company->user_id,
            'company_id' => $company->id,
            'package_id' => $package->id,
            'subscription_id' => $subscription->id,
            'kode_voucher' => Voucher::generateUniqueCode(),
            'status' => 'active',
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(29),
        ]);
    }
}

if (! function_exists('makeLimitMetric')) {
    function makeLimitMetric(string $key, string $type = LimitMetric::TYPE_CONSUMABLE): LimitMetric
    {
        return LimitMetric::create([
            'key' => $key,
            'name' => ucfirst(str_replace('_', ' ', $key)),
            'metric_type' => $type,
            'category_application_id' => null,
        ]);
    }
}
