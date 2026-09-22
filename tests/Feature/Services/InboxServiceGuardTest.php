<?php

/**
 * CLAUDE.md checklist item #11 — covers the actual integration point
 * checklist item #3 (Fase 1) built: InboxService::send()'s opt-in
 * $company guard (guardPackageLimit()/releasePackageLimit(), see that
 * class's docblocks). PackageLimitServiceTest.php already covers
 * reserve()/release() in isolation; this file exists to prove the two
 * are actually wired together correctly at the one call site a real
 * WA-send goes through — in particular the exact bug the try/catch
 * around request() in send() exists to prevent: a failed HTTP call to
 * the Go backend must give back the quota unit reserve() took, not
 * burn it permanently.
 *
 * Http::fake() stands in for the Go backend itself — no real network
 * call is made. $jwt is just an opaque string as far as InboxService is
 * concerned (it only forwards it as a Bearer header), so no
 * SystemJwtService/real user session is needed here.
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
use App\Services\Chat\InboxService;
use Illuminate\Support\Facades\Http;

function usageFor(Company $company, LimitMetric $metric): int
{
    return (int) (CompanyLimitUsage::where('company_id', $company->id)
        ->where('limit_metric_id', $metric->id)
        ->first()
        ->used_value ?? 0);
}

// =========================================================
// $company omitted (default null) — must behave exactly as before
// checklist #3 existed: no guard, no quota touched at all.
// =========================================================

test('send() without a $company does not touch any quota, even for a company with no active package', function () {
    Http::fake([
        '*/api/wa/devices/*/chats/*/messages' => Http::response(['message' => ['id' => 'abc']], 200),
    ]);

    $result = app(InboxService::class)->send('fake-jwt', 'device-1', '6281234567890@s.whatsapp.net', 'hello');

    expect($result)->toBe(['id' => 'abc']);
    Http::assertSentCount(1);
});

// =========================================================
// $company passed — guard runs BEFORE the HTTP call is ever made
// =========================================================

test('send() with $company throws before making any HTTP call when the company has no active package', function () {
    Http::fake();
    $company = makeCompany();

    expect(fn () => app(InboxService::class)->send(
        'fake-jwt', 'device-1', '6281234567890@s.whatsapp.net', 'hello', $company
    ))->toThrow(PackageLimitExceededException::class);

    Http::assertNothingSent();
});

test('send() with $company throws before making any HTTP call once the metric is already at its limit', function () {
    Http::fake();
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 1]);
    CompanyLimitUsage::create(['company_id' => $company->id, 'limit_metric_id' => $metric->id, 'used_value' => 1]);

    expect(fn () => app(InboxService::class)->send(
        'fake-jwt', 'device-1', '6281234567890@s.whatsapp.net', 'hello', $company
    ))->toThrow(PackageLimitExceededException::class);

    Http::assertNothingSent();
    expect(usageFor($company, $metric))->toBe(1); // unchanged
});

// =========================================================
// Successful send — quota is reserved and stays reserved
// =========================================================

test('send() with $company reserves one unit of quota on a successful send', function () {
    Http::fake([
        '*/api/wa/devices/*/chats/*/messages' => Http::response(['message' => ['id' => 'abc']], 200),
    ]);
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 10]);

    app(InboxService::class)->send('fake-jwt', 'device-1', '6281234567890@s.whatsapp.net', 'hello', $company);

    expect(usageFor($company, $metric))->toBe(1);
});

// =========================================================
// Failed send — the exact case guardPackageLimit()/releasePackageLimit()
// exist for: a failed HTTP call must give the quota unit back.
// =========================================================

test('send() releases the reserved quota unit when the Go backend call fails', function () {
    Http::fake([
        '*/api/wa/devices/*/chats/*/messages' => Http::response(['error' => 'wa: device is not connected'], 502),
    ]);
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 10]);

    expect(fn () => app(InboxService::class)->send(
        'fake-jwt', 'device-1', '6281234567890@s.whatsapp.net', 'hello', $company
    ))->toThrow(RuntimeException::class);

    // The failed send must not have permanently burned quota it never
    // actually used — this is what release() in send()'s catch block
    // is for.
    expect(usageFor($company, $metric))->toBe(0);
});

test('a failed send followed by a successful retry only ever holds one reserved unit, not two', function () {
    Http::fakeSequence('*/api/wa/devices/*/chats/*/messages')
        ->push(['error' => 'wa: device is not connected'], 502)
        ->push(['message' => ['id' => 'abc']], 200);

    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 10]);

    $service = app(InboxService::class);

    try {
        $service->send('fake-jwt', 'device-1', '6281234567890@s.whatsapp.net', 'hello', $company);
    } catch (RuntimeException) {
        // expected — quota was released, see the test above
    }

    $service->send('fake-jwt', 'device-1', '6281234567890@s.whatsapp.net', 'hello (retry)', $company);

    expect(usageFor($company, $metric))->toBe(1);
});

// =========================================================
// $limitMetric = null — package must still be active, but no quota
// row is ever touched (used by auto-reply/AI bot per InboxService's
// guardPackageLimit() docblock — checklist item #8, still open).
// =========================================================

test('send() with $company but $limitMetric=null still requires an active package but never touches CompanyLimitUsage', function () {
    Http::fake([
        '*/api/wa/devices/*/chats/*/messages' => Http::response(['message' => ['id' => 'abc']], 200),
    ]);
    $company = makeCompany();
    $package = makePackage();
    makeActiveVoucher($company, $package);
    $metric = makeLimitMetric('broadcast_send');
    PackageLimit::create(['package_id' => $package->id, 'limit_metric_id' => $metric->id, 'max_value' => 10]);

    app(InboxService::class)->send(
        'fake-jwt', 'device-1', '6281234567890@s.whatsapp.net', 'hello', $company, null
    );

    expect(CompanyLimitUsage::where('company_id', $company->id)->where('limit_metric_id', $metric->id)->exists())
        ->toBeFalse();
});

test('send() with $company but $limitMetric=null still throws when the company has no active package at all', function () {
    Http::fake();
    $company = makeCompany();

    expect(fn () => app(InboxService::class)->send(
        'fake-jwt', 'device-1', '6281234567890@s.whatsapp.net', 'hello', $company, null
    ))->toThrow(PackageLimitExceededException::class);

    Http::assertNothingSent();
});

// =========================================================
// Fixture helpers — shared with PackageLimitServiceTest.php's own
// copies (Pest loads every test file into the same global scope, so
// these must NOT be redefined here — see note below).
// =========================================================

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
