<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stops the browser back/forward button from resurrecting a page from its
 * bfcache (back-forward cache) or disk cache after the user has logged out.
 *
 * The bug this fixes: 'auth' middleware only runs when a request actually
 * reaches the server. If the browser has cached the rendered HTML of, say,
 * dashboard/package/{package}/checkout, hitting Back after logout can
 * redisplay that cached page directly from the browser's own cache —
 * the request never leaves the client, so 'auth' never gets a chance to
 * reject it and redirect to /login. This is a browser-caching problem, not
 * a session/auth problem, so it can't be fixed by changing 'auth' itself.
 *
 * The fix is to tell the browser, on every response, not to keep this page
 * around at all: Cache-Control: no-store (plus the legacy Pragma/Expires
 * pair for older caches). With no-store, Back/Forward is forced to
 * re-request the page from the server instead of repainting a cached copy
 * — and that fresh request DOES go through 'auth', which now correctly
 * bounces a logged-out user to /login. Do this on every Back press and the
 * user is bounced to /login every single time, not just the first.
 *
 * Registered on the whole 'web' middleware group (see bootstrap/app.php)
 * rather than only on 'auth' routes: applying it once, globally, means any
 * new authenticated route added later (payment, checkout, or otherwise) is
 * covered automatically without anyone having to remember to tag it — same
 * reasoning as EnsureActivePackage being applied at the route-group level
 * instead of per-route. Running it on guest pages (login, the public
 * landing page) too is harmless: it only turns off browser caching, it
 * doesn't touch authentication itself.
 *
 * Skips file download responses (BinaryFileResponse / StreamedResponse —
 * e.g. CompanyUserController::export()) so it doesn't fight whatever
 * caching/disposition headers those already set for the browser's
 * download handling.
 */
class PreventBackHistoryCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $response;
        }

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
