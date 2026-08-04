<?php

namespace App\Http\Middleware;

use App\Models\ApplicationMenu;
use App\Models\CompanyRoleMenu;
use App\Services\Company\CompanyContextResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces, at the route level, what App\Models\CompanyRoleMenu already
 * enforces visually in the sidebar (resources/views/layouts/partials/
 * menu.blade.php) — hiding a link is not access control, since the
 * underlying route is still reachable by typing the URL directly. This
 * middleware is that backstop for the `chat` route group.
 *
 * Only ever restricts a NON-owner member with a resolved CompanyRole
 * (see App\Services\Company\CompanyContextResolver) — the company owner
 * ("pusat") is unrestricted, same as everywhere else in this app. A
 * route whose feature was never added to the App\Models\ApplicationMenu
 * catalog (no matching `route_name`) fails OPEN rather than blocking
 * something nobody's cataloged yet — this middleware can only take
 * access away from what's actually been registered, never silently
 * lock out a feature by omission.
 *
 * Matched by the route name's first two segments ("chat.<feature>"),
 * not the exact route name — a feature's create/store/update/destroy/
 * history actions all share one ApplicationMenu catalog entry (the
 * ".index" route), since they're all "the same menu item" as far as
 * access is concerned.
 */
class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user || $user->user_type === 'SUPERADMIN') {
            return $next($request);
        }

        $context = app(CompanyContextResolver::class)->resolve($user);

        // No company context at all, or the owner acting on their own
        // company: unrestricted. A user with no context yet is stopped
        // by earlier gates (auth, active.package) long before this
        // matters.
        if (! $context || $context->isOwner) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return $next($request);
        }

        $routeGroup = implode('.', array_slice(explode('.', $routeName), 0, 2));

        $menu = ApplicationMenu::where('route_name', 'like', $routeGroup.'.%')->first();

        if (! $menu) {
            return $next($request);
        }

        $allowed = $context->role
            ? CompanyRoleMenu::where('company_role_id', $context->role->id)
                ->where('application_menu_id', $menu->id)
                ->where('status', 'active')
                ->exists()
            : false;

        if (! $allowed) {
            return $this->deny($request);
        }

        return $next($request);
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
