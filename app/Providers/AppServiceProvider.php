<?php

namespace App\Providers;

use App\Models\JadwalReminderSetting;
use App\Services\Company\ActiveBranchSelection;
use App\Services\Company\CompanyContextResolver;
use App\Services\PackageLimitService;
use App\Support\MenuGateCategories;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rules\Password;
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
        // Aturan password untuk register, reset & ganti password
        // (Password::defaults()): minimal 8 karakter, ada huruf dan angka.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Sidebar (menu.blade.php) dan pemilih branch (header.blade.php)
        // di-share oleh setiap halaman dashboard, bukan dirender satu
        // controller -- view composer menghitung ulang datanya tepat
        // sebelum partial dirender.
        //
        // Menu mengikuti PAKET BRANCH YANG SEDANG DIBUKA (paket berlaku
        // per branch, 25 September 2026), dihitung lewat
        // PackageLimitService yang sama dengan middleware
        // EnsureActivePackage -- jadi menu yang disembunyikan di sini juga
        // pasti ditolak kalau URL-nya diketik langsung. Superadmin melihat
        // semua (bukan pelanggan berbayar).
        View::composer('layouts.partials.menu', function (\Illuminate\View\View $view) {
            $user = Auth::user();
            $context = $user && $user->user_type !== 'SUPERADMIN'
                ? app(CompanyContextResolver::class)->resolve($user, session('active_company_id'))
                : null;

            $isSuperadmin = $user?->user_type === 'SUPERADMIN';
            $branch = $context?->activeBranch();
            $packageLimits = app(PackageLimitService::class);

            $covers = fn (array $names) => $isSuperadmin
                || ($branch && $packageLimits->hasActiveCategoryPackage($context->company, $names, $branch));

            $view->with([
                'hasActivePackage' => $isSuperadmin || ($branch && $packageLimits->activePackage($context->company, $branch) !== null),
                'hasActiveChatPackage' => $covers(JadwalReminderSetting::CHAT_CATEGORY_NAMES),
                'hasActiveFormPackage' => $covers(MenuGateCategories::FORM_CATEGORY_NAMES),
                'hasActiveJadwalPackage' => $covers(MenuGateCategories::JADWAL_CATEGORY_NAMES),
                'hasActiveTagihanPackage' => $covers(MenuGateCategories::TAGIHAN_CATEGORY_NAMES),
                // Aturan menu per role -- sama dengan middleware
                // 'menu.access', lihat CompanyContext::canAccessRoute().
                'canSeeMenu' => fn (string $routeName) => ! $context || $context->canAccessRoute($routeName),
            ]);
        });

        // Pemilih branch di header -- hanya muncul untuk user yang boleh
        // membuka lebih dari satu branch (owner / member tingkat company).
        View::composer('layouts.partials.header', function (\Illuminate\View\View $view) {
            $user = Auth::user();
            $context = $user && $user->user_type !== 'SUPERADMIN'
                ? app(CompanyContextResolver::class)->resolve($user, session('active_company_id'))
                : null;

            $view->with([
                'switchableBranches' => $context && $context->seesAllBranches()
                    ? app(ActiveBranchSelection::class)->branchesFor($context)
                    : collect(),
                'activeBranch' => $context?->activeBranch(),
            ]);
        });
    }
}
