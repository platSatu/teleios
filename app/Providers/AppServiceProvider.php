<?php

namespace App\Providers;

use App\Models\Voucher;
use App\Services\Company\CompanyContextResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // resources/views/layouts/partials/menu.blade.php is a shared
        // sidebar partial included by every dashboard page (layouts/
        // dashboard.blade.php), not rendered by one single controller —
        // so there's no single place upstream to pass $hasActivePackage
        // into it the way ProfileController does for its own page. A
        // view composer fills that gap: it recomputes the flag fresh,
        // right before the partial renders, on every page.
        //
        // Same source of truth and query as App\Http\Middleware\
        // EnsureActivePackage (status='active' AND valid_until >= now())
        // and User\Profile\Concerns\ScopesActivePackage — this used to be
        // the one place that check was missing, so the Chat menu (and
        // its "Pengaturan" sub-menu: Pesan Terjadwal, Balasan Otomatis,
        // etc.) stayed visible and clickable in the sidebar even after
        // every voucher a user held had expired. The routes themselves
        // were always correctly blocked by the middleware — this was a
        // UI-only gap where the link still looked reachable. Superadmins
        // bypass the same way EnsureActivePackage does: they aren't a
        // paying customer subject to package expiry.
        View::composer('layouts.partials.menu', function (\Illuminate\View\View $view) {
            $user = Auth::user();

            // Same "check the company OWNER's voucher, not the logged-in
            // member's own" fix as App\Http\Middleware\EnsureActivePackage
            // — otherwise an Admin/Staff member invited via
            // User\Profile\CompanyUserController would never see the Chat
            // menu at all, package or no package, since they never redeem
            // a voucher themselves.
            $billingUserId = $user
                ? (app(CompanyContextResolver::class)->resolve($user)?->company->user_id ?? $user->id)
                : null;

            $hasActivePackage = $user && (
                $user->user_type === 'SUPERADMIN'
                || Voucher::query()
                    ->where('user_id', $billingUserId)
                    ->where('status', 'active')
                    ->whereNotNull('valid_from')
                    ->whereNotNull('valid_until')
                    ->where('valid_from', '<=', now())
                    ->where('valid_until', '>=', now())
                    ->exists()
            );

            // Which Chat route_names (App\Models\ApplicationMenu catalog)
            // the logged-in user is allowed to actually click through to
            // — null means "unrestricted" (owner/superadmin/no company
            // context), a Collection means "only these". This is what
            // makes the sidebar itself differ per CompanyRole, not just
            // the route-level 'menu.access' middleware backstop (see
            // App\Http\Middleware\EnsureMenuAccess) — a link a role can't
            // use shouldn't be shown at all, not just clickable-then-403.
            $allowedChatRouteNames = null;

            if ($user && $user->user_type !== 'SUPERADMIN') {
                $context = app(\App\Services\Company\CompanyContextResolver::class)->resolve($user);

                if ($context && ! $context->isOwner) {
                    $allowedChatRouteNames = $context->role
                        ? \App\Models\CompanyRoleMenu::where('company_role_id', $context->role->id)
                            ->where('status', 'active')
                            ->with('applicationMenu:id,route_name')
                            ->get()
                            ->pluck('applicationMenu.route_name')
                            ->filter()
                            ->values()
                        : collect();
                }
            }

            $view->with('hasActivePackage', $hasActivePackage);
            $view->with('allowedChatRouteNames', $allowedChatRouteNames);
        });
    }
}
