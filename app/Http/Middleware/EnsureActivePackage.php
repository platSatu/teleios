<?php

namespace App\Http\Middleware;

use App\Services\Company\CompanyContextResolver;
use App\Services\PackageLimitService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kunci route (atau satu grup prefix) di balik "apakah BRANCH yang sedang
 * dibuka user ini punya paket aktif" -- paket berlaku per branch (alur
 * Company -> Branch -> Paket, 25 September 2026). Branch yang dibuka
 * diambil dari CompanyContext::activeBranch() (member terkunci = branch-
 * nya sendiri, owner = pilihan di pemilih branch header).
 *
 * Pengecekan paketnya sendiri didelegasikan ke App\Services\
 * PackageLimitService -- satu sumber kebenaran yang sama dengan sidebar,
 * job, dan pengiriman WA, jadi menu yang tersembunyi di sidebar juga
 * pasti tertolak kalau URL-nya diketik langsung.
 *
 * Opsional filter layanan per nama category, mis.
 * 'active.package:Chat,WhatsApp' -- hanya paket yang MENCAKUP salah satu
 * layanan itu yang dihitung (paket bisa multi-layanan, lihat
 * Package::scopeCoveringCategoryNames()). Tanpa argumen = paket apa saja.
 *
 * Superadmin selalu lolos (bukan pelanggan berbayar).
 */
class EnsureActivePackage
{
    /**
     * @param  string  ...$categories  Filter category_applications.name (opsional).
     */
    public function handle(Request $request, Closure $next, string ...$categories): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $this->deny($request, 'Silakan login terlebih dahulu.', 401, 'login');
        }

        if ($user->user_type === 'SUPERADMIN') {
            return $next($request);
        }

        $context = app(CompanyContextResolver::class)->resolve($user, session('active_company_id'));
        $branch = $context?->activeBranch();

        if (! $context || ! $branch) {
            return $this->deny(
                $request,
                'Anda belum memiliki branch. Buat branch terlebih dahulu, lalu pilih paket untuk branch tersebut.',
                403,
                'package_expired'
            );
        }

        $packageLimits = app(PackageLimitService::class);

        $hasActivePackage = $categories === []
            ? $packageLimits->activePackage($context->company, $branch) !== null
            : $packageLimits->hasActiveCategoryPackage($context->company, $categories, $branch);

        if (! $hasActivePackage) {
            return $this->deny(
                $request,
                "Branch {$branch->name} tidak memiliki paket aktif untuk menu ini. Beli/perpanjang paket untuk branch ini, atau pilih branch lain.",
                403,
                'package_expired'
            );
        }

        return $next($request);
    }

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
