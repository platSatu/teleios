<?php

namespace App\Http\Middleware;

use App\Models\Voucher;
use App\Services\Company\CompanyContextResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route (or a whole prefix group) behind "does this user currently
 * have at least one ACTIVE, not-yet-expired package".
 *
 * Source of truth is App\Models\Voucher (status = 'active' AND
 * valid_until >= now(), down to the minute) — deliberately NOT
 * App\Models\Subscription.
 * Subscription is stamped ACTIVE the instant a purchase happens (it's the
 * billing/purchase record — see Dashboard\PackageCheckoutController).
 * Voucher.valid_from/valid_until is the thing that's only "switched on"
 * once the user actually redeems it (Dashboard\VoucherRedeemController) —
 * that's this app's real "is the package usable right now" clock, and
 * it's the same field VoucherRedeemController accumulates on repeat
 * redemptions, so it stays correct even when a user stacks vouchers.
 *
 * Usage — block an entire prefix group, so any route added under it later
 * is protected automatically without having to remember to tag it:
 *
 *   Route::prefix('chat')->middleware(['auth', 'verified', 'active.package'])
 *       ->group(function () { ... });
 *
 * Optionally restrict to specific package categories by name, e.g.
 * 'active.package:Chat,WhatsApp' — only a voucher whose package belongs
 * to a category_application named "Chat" or "WhatsApp" counts. Omit the
 * argument to accept ANY currently active package regardless of category.
 *
 * Backward-compat rule (Tahap 4 integrasi Chat<->Jadwal): a package that
 * has never been tagged with ANY category_application (category_application_id
 * still NULL — every package created before this app started tagging
 * categories) counts as passing EVERY category filter, not just some
 * default one. Without this, turning on 'active.package:Chat,WhatsApp'
 * for the Chat route group would have instantly locked out every
 * customer whose still-active package predates the category feature —
 * they never touched a "category" concept, so it would be wrong to
 * treat them as belonging to none. A package explicitly tagged to a
 * DIFFERENT category (e.g. a "Jadwal" package) is NOT covered by this —
 * it has a category, just not one of the ones being asked for here.
 */
class EnsureActivePackage
{
    /**
     * @param  string  ...$categories  Optional category_applications.name
     *                                 filter (case handling depends on DB
     *                                 collation — MySQL's default is
     *                                 case-insensitive).
     */
    public function handle(Request $request, Closure $next, string ...$categories): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $this->deny($request, 'Silakan login terlebih dahulu.', 401, 'login');
        }

        // Superadmins run the platform, they aren't a paying customer
        // subject to package expiry. Remove this bypass if superadmins
        // should also be gated by an active package.
        if ($user->user_type === 'SUPERADMIN') {
            return $next($request);
        }

        // A company's package is bought and redeemed by its OWNER, not by
        // every member individually — an Admin/Staff invited via
        // User\Profile\CompanyUserController never holds a voucher
        // themselves, so checking $user->id here would lock every one of
        // them out even while their company's package is perfectly valid.
        // Resolve to the acting company's owner (App\Services\Company\
        // CompanyContextResolver) and check THEIR voucher instead; falls
        // back to the logged-in user's own id if they're not part of any
        // company yet (e.g. before they've created one).
        $context = app(CompanyContextResolver::class)->resolve($user);
        $billingUserId = $context?->company->user_id ?? $user->id;

        $hasActivePackage = Voucher::query()
            ->where('user_id', $billingUserId)
            ->where('status', 'active')
            // Belum pernah di-redeem (masih 'pending') sudah gugur lewat
            // status='active' di atas, tapi valid_from/valid_until
            // ditambahkan eksplisit sebagai lapisan kedua — kalau suatu
            // saat ada baris 'active' yang datanya tidak lengkap (belum
            // benar-benar di-redeem, valid_from/valid_until masih NULL),
            // baris itu TETAP dianggap tidak aktif, bukan lolos begitu
            // saja karena NULL >= now() sebenarnya sudah false di MySQL,
            // tapi ini dibuat eksplisit supaya jelas dan tidak bergantung
            // pada perilaku implisit NULL comparison.
            ->whereNotNull('valid_from')
            ->whereNotNull('valid_until')
            // Voucher yang jatah aktifnya belum mulai (valid_from di masa
            // depan) juga belum boleh dipakai.
            ->where('valid_from', '<=', now())
            // Was whereDate('valid_until', '>=', today) — that rounded
            // every expiry up to end-of-day regardless of the hour the
            // voucher was actually redeemed. valid_until is a real
            // datetime now (see 2026_07_31_050000_change_vouchers_valid_dates_to_datetime),
            // so this compares down to the minute like it always should
            // have.
            ->where('valid_until', '>=', now())
            ->when($categories !== [], function ($query) use ($categories) {
                $query->where(function ($q) use ($categories) {
                    $q->whereHas(
                        'package.categoryApplication',
                        fn ($qq) => $qq->whereIn('name', $categories)
                    )
                        // Backward-compat fallback -- lihat docblock class
                        // ini di atas.
                        ->orWhereDoesntHave('package.categoryApplication');
                });
            })
            ->exists();

        if (! $hasActivePackage) {
            return $this->deny(
                $request,
                'Masa aktif package Anda sudah habis. Silakan redeem voucher, beli package baru, atau hubungi administrator untuk melanjutkan.',
                403,
                'package_expired'
            );
        }

        return $next($request);
    }

    /**
     * Most of these chat routes are polled/called via fetch() from the
     * frontend, so an AJAX/JSON caller gets a clean JSON error body it
     * can actually parse and act on (e.g. stop polling, show a toast,
     * redirect client-side) instead of an HTML page it can't do anything
     * useful with.
     *
     * A plain browser navigation gets a real, branded blocking page
     * instead — same idea as SuperadminMiddleware's abort(403) for
     * dashboard/superadmin/*, except with actual next steps (redeem
     * voucher / buy package) instead of a bare "403 | Unauthorized"
     * screen, since "package_expired" is a state the user can resolve
     * themselves. "login" keeps the old redirect+flash behaviour — no
     * dedicated page needed for "you're not logged in".
     */
    protected function deny(Request $request, string $message, int $status, string $reason): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'error' => $message,
                'reason' => $reason,
            ], $status);
        }

        if ($reason === 'package_expired') {
            return response()
                ->view('dashboard.package.expired', ['message' => $message], $status);
        }

        return redirect()
            ->route('login')
            ->with('error', $message);
    }
}
