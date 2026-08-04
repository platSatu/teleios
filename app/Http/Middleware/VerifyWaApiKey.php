<?php

namespace App\Http\Middleware;

use App\Models\WaApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a THIRD PARTY's request (no logged-in user, no session
 * at all) against App\Models\WaApiKey — the token+secret_key pair
 * generated per-device from the Device page (see App\Http\Controllers\
 * Chat\WaApiKeyController). This is the ONLY gate in front of
 * App\Http\Controllers\Api\WaApiSendMessageController; there's no `auth`
 * middleware anywhere on that route.
 *
 * Credentials are read from `X-WA-Token` / `X-WA-Secret` headers (not
 * the request body) — keeps them out of server access logs that record
 * query strings/bodies less consistently than headers, and mirrors how
 * `X-API-KEY` already works for the internal Laravel<->Go link (see
 * App\Services\Chat\InboxService).
 *
 * On success, the resolved WaApiKey is attached to the request
 * (`$request->attributes->set('waApiKey', ...)`) so the controller never
 * re-queries it, and `last_used_at` is stamped — the only place in this
 * app that column is ever written.
 */
class VerifyWaApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-WA-Token');
        $secret = $request->header('X-WA-Secret');

        if (! $token || ! $secret) {
            return response()->json([
                'error' => 'Header X-WA-Token dan X-WA-Secret wajib diisi.',
            ], 401);
        }

        $apiKey = WaApiKey::where('token', $token)
            ->where('status', 'active')
            ->first();

        // Compared with hash_equals rather than a plain === — this is a
        // credential check, and a naive string comparison is vulnerable
        // to a timing attack (early-exit on first differing byte) that
        // hash_equals is specifically designed to resist.
        if (! $apiKey || ! hash_equals($apiKey->secret_key, (string) $secret)) {
            return response()->json([
                'error' => 'Token atau Secret Key tidak valid.',
            ], 401);
        }

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('waApiKey', $apiKey);

        return $next($request);
    }
}
