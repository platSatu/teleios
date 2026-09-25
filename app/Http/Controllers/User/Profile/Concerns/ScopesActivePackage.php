<?php

namespace App\Http\Controllers\User\Profile\Concerns;

use App\Models\Voucher;
use Illuminate\Support\Collection;

/**
 * Shared "does this user currently have an active package" check for the
 * three profile tabs that only make sense once a company has actually
 * paid for something (Setting Users, Roles, Applications) — see
 * ProfileController's class docblock for the tab list, and each
 * controller that `use`s this trait for exactly where it's enforced.
 *
 * Same source of truth as App\Http\Middleware\EnsureActivePackage:
 * an ACTIVE, not-yet-expired App\Models\Voucher — deliberately NOT
 * App\Models\Subscription (stamped ACTIVE the instant a purchase
 * happens, before the package is actually usable). See that
 * middleware's docblock for the full rationale.
 */
trait ScopesActivePackage
{
    /**
     * category_application_id of every package this user currently has
     * an active (redeemed, not expired) voucher for. Empty collection
     * means no active package at all — callers treat that as "gate
     * closed" rather than "no category filter".
     */
    protected function activeCategoryApplicationIds(string $userId): Collection
    {
        // Semua layanan dari SEMUA paket aktif company (lintas branch) --
        // dipakai layar setup tim (role, anggota, menu per role), yang
        // memang tingkat company. Paket bisa multi-layanan, jadi id
        // diambil dari Package::categoryIds() (pivot + category utama).
        return Voucher::query()
            ->currentlyActive()
            ->where('user_id', $userId)
            ->with('package.categoryApplications')
            ->get()
            ->flatMap(fn (Voucher $voucher) => $voucher->package?->categoryIds() ?? [])
            ->unique()
            ->values();
    }
}
