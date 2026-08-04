<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates inbound webhook routes Go calls INTO Laravel (currently only
 * POST /api/webhooks/wa/incoming-message, see
 * App\Http\Controllers\Api\WaIncomingMessageWebhookController) — the
 * mirror image of App\Services\Chat\InboxService, which is Laravel
 * calling OUT to Go with the same header. Both directions trust the
 * same shared secret (services.golang.key / Go's SECRET_API_KEY — see
 * App\Services\Chat\SystemJwtService's docblock for how that was
 * confirmed to be the identical value on both sides).
 *
 * Deliberately not behind 'auth'/'verified': this is a server-to-server
 * call from the Go process, not a logged-in user's browser — there's no
 * session, no user JWT, nothing else to check here.
 */
class VerifyGolangApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.golang.key');
        $provided = $request->header('X-API-KEY');

        if (! $expected || ! $provided || ! hash_equals($expected, $provided)) {
            // Rejected before the controller ever runs — without this,
            // a key mismatch (e.g. Go and Laravel .env drifting apart)
            // looks identical to "the webhook was never called at all"
            // in every other log.
            Log::warning('VerifyGolangApiKey: rejected request', [
                'path' => $request->path(),
                'has_expected_key' => (bool) $expected,
                'has_provided_key' => (bool) $provided,
            ]);

            return response()->json(['error' => 'Invalid or missing API key.'], 401);
        }

        return $next($request);
    }
}
