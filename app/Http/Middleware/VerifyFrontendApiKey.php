<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the read-only catalog routes under /api/frontend (currently
 * category-applications and packages — see App\Http\Controllers\Api\
 * Frontend\CategoryApplicationController and PackageController) that
 * the fe-konexa app calls server-to-server (see fe-konexa's
 * App\Services\TeleiosApiService) to render its public landing pages.
 * Same trust model as VerifyGolangApiKey: a shared secret compared with
 * hash_equals, not auth:sanctum, since there's no logged-in user on the
 * calling side at all — just one Laravel app's backend calling another's.
 *
 * The secret lives in services.frontend.key (FRONTEND_API_KEY in this
 * app's .env) and must match TELEIOS_API_KEY in fe-konexa's .env
 * exactly — both apps run as separate `php artisan serve` processes on
 * localhost during development, on different ports (see SERVER_PORT in
 * each .env) since they can't both bind to the default :8000.
 */
class VerifyFrontendApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.frontend.key');
        $provided = $request->header('X-API-KEY');

        if (! $expected || ! $provided || ! hash_equals($expected, $provided)) {
            Log::warning('VerifyFrontendApiKey: rejected request', [
                'path' => $request->path(),
                'has_expected_key' => (bool) $expected,
                'has_provided_key' => (bool) $provided,
            ]);

            return response()->json(['error' => 'Invalid or missing API key.'], 401);
        }

        return $next($request);
    }
}
