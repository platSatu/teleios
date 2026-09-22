<?php

namespace App\Providers;

use App\Models\JadwalReminderSetting;
use App\Models\Voucher;
use App\Services\Company\CompanyContextResolver;
use App\Support\MenuGateCategories;
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

            // Category-scoped versions of $hasActivePackage above.
            // $hasActiveChatPackage dipakai menu "Pengaturan Pengingat"
            // Jadwal (lihat App\Services\PackageLimitService::
            // hasActiveCategoryPackage()'s docblock untuk kenapa ini
            // query terpisah, bukan filter tambahan ke $hasActivePackage
            // yang sudah ada).
            //
            // $hasActiveFormPackage / $hasActiveJadwalPackage (22
            // September 2026) gate the Form and Jadwal sidebar sections
            // THEMSELVES — those two modules used to be shown to every
            // logged-in user regardless of package (see menu.blade.php's
            // git history / routes/web.php's old comments), deliberately
            // changed so Form/Jadwal/WhatsApp are sold and gated as fully
            // independent services: a company holding only a WhatsApp
            // Blast package does NOT get Form/Jadwal for free, and vice
            // versa. See App\Support\MenuGateCategories's docblock.
            //
            // All three sengaja dihitung dengan query company_id langsung
            // (bukan lewat $billingUserId/user_id di atas), konsisten
            // dengan pola PackageLimitService yang lain
            // (resolveActiveVoucher() dkk), supaya cara menghitungnya
            // tetap satu sumber kebenaran kalau dipanggil ulang dari
            // controller/job.
            $hasActiveChatPackage = false;
            $hasActiveFormPackage = false;
            $hasActiveJadwalPackage = false;

            if ($user) {
                if ($user->user_type === 'SUPERADMIN') {
                    $hasActiveChatPackage = true;
                    $hasActiveFormPackage = true;
                    $hasActiveJadwalPackage = true;
                } else {
                    $chatContext = app(CompanyContextResolver::class)->resolve($user);

                    if ($chatContext?->company) {
                        $packageLimits = app(\App\Services\PackageLimitService::class);

                        $hasActiveChatPackage = $packageLimits
                            ->hasActiveCategoryPackage($chatContext->company, JadwalReminderSetting::CHAT_CATEGORY_NAMES);
                        $hasActiveFormPackage = $packageLimits
                            ->hasActiveCategoryPackage($chatContext->company, MenuGateCategories::FORM_CATEGORY_NAMES);
                        $hasActiveJadwalPackage = $packageLimits
                            ->hasActiveCategoryPackage($chatContext->company, MenuGateCategories::JADWAL_CATEGORY_NAMES);
                    }
                }
            }

            $view->with('hasActivePackage', $hasActivePackage);
            $view->with('hasActiveChatPackage', $hasActiveChatPackage);
            $view->with('hasActiveFormPackage', $hasActiveFormPackage);
            $view->with('hasActiveJadwalPackage', $hasActiveJadwalPackage);
            $view->with('allowedChatRouteNames', $allowedChatRouteNames);
        });
    }
}
