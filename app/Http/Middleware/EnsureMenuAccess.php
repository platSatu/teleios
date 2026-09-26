<?php

namespace App\Http\Middleware;

use App\Services\Company\CompanyContextResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga URL untuk aturan menu per role -- sama persis dengan yang
 * dipakai sidebar, lihat App\Services\Company\CompanyContext::
 * canAccessRoute(). Menyembunyikan link bukan kontrol akses; route-nya
 * tetap bisa diketik langsung, middleware inilah yang menolaknya.
 *
 * Superadmin, owner, dan user yang belum punya company sama sekali
 * (belum bisa berbuat apa-apa di route bertanda ini) dilewatkan. Member
 * company hanya lolos kalau menunya sudah diberikan ke role-nya --
 * selain itu ditolak (fail-closed).
 */
class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $user->user_type === 'SUPERADMIN') {
            return $next($request);
        }

        $context = app(CompanyContextResolver::class)->resolve($user, session('active_company_id'));

        if (! $context || $context->canAccessRoute($request->route()?->getName())) {
            return $next($request);
        }

        return $this->deny($request);
    }

    protected function deny(Request $request): Response
    {
        $message = 'Anda tidak memiliki akses ke menu ini. Hubungi admin/owner company Anda.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['error' => $message], 403);
        }

        abort(403, $message);
    }
}
