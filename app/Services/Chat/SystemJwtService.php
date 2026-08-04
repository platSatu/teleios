<?php

namespace App\Services\Chat;

use App\Models\User;

/**
 * Mints a short-lived JWT the Go backend will accept exactly like the
 * one it issues on login, for calls that happen with no logged-in user
 * in the loop — right now, only App\Jobs\SendScheduledWaMessage.
 *
 * This works with zero changes to the Go backend because
 * config('services.golang.key') (Laravel's GOLANG_API_KEY) and Go's
 * SECRET_API_KEY are the *same* shared secret (confirmed against
 * g_backend/.env directly) — internal/service/auth-service.go signs its
 * login JWTs HS256 with that exact value, and ValidateToken() only
 * checks the signature + standard exp/iat claims, no server-side session
 * store to match against. So a token minted here, with the same claim
 * shape Go's Claims struct expects (user_id, email, exp, iat), is
 * indistinguishable to Go from one issued through a real login — Go's
 * per-request device-ownership check (AssertOwnership(userID, deviceID))
 * still applies exactly as normal, so this can't be used to act as a
 * user who doesn't actually own the target device.
 *
 * Deliberately NOT a general-purpose "impersonate any user" helper:
 * only call this with the user_id that actually owns the device/company
 * being acted on (see SendScheduledWaMessage, which resolves it from
 * the schedule's company owner). Tokens are single-purpose and
 * short-lived (5 minutes) specifically to limit blast radius if one
 * ever ended up somewhere it shouldn't (a queue log, a stack trace).
 *
 * No external JWT library: composer isn't available in every
 * environment this ships to, and HS256 is simple enough (base64url
 * header + payload, HMAC-SHA256 signature) to hand-roll safely rather
 * than add a dependency for one call site.
 */
class SystemJwtService
{
    protected const TTL_SECONDS = 300;

    public function mintFor(User $user): string
    {
        $secret = config('services.golang.key');

        if (! $secret) {
            throw new \RuntimeException('services.golang.key (GOLANG_API_KEY) is not configured — cannot mint a system JWT.');
        }

        $now = time();

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            // Field names match Go's service.Claims struct exactly
            // (internal/service/auth-service.go) — it decodes these by
            // JSON key, not by position.
            'user_id' => $user->id,
            'email' => $user->email,
            'iat' => $now,
            'exp' => $now + self::TTL_SECONDS,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
