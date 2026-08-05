<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

/**
 * Server-side check for the `cf-turnstile-response` field the Cloudflare
 * Turnstile JS widget (see resources/views/auth/{login,register,
 * forgot-password}.blade.php) submits alongside those forms — used by
 * App\Http\Controllers\Auth\AuthController::login()/register()/
 * forgotPassword(). Calls Cloudflare's own siteverify endpoint; the
 * widget token by itself only proves *a* browser ran *some* challenge,
 * this is what actually confirms Cloudflare approved it — without this
 * server-side call, anyone could just POST an arbitrary non-empty string
 * in that field and skip the captcha entirely.
 */
class Turnstile implements ValidationRule
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secretKey = config('services.turnstile.secret_key');

        // Fail OPEN (skip the check) if this deployment hasn't
        // configured Turnstile at all — e.g. a fresh local clone without
        // TURNSTILE_SECRET_KEY set — rather than permanently locking
        // every login/register/forgot-password attempt.
        if (! $secretKey) {
            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('Verifikasi captcha gagal. Silakan coba lagi.');

            return;
        }

        try {
            $response = Http::asForm()->post(self::VERIFY_URL, [
                'secret' => $secretKey,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            $fail('Verifikasi captcha sedang bermasalah. Silakan coba lagi.');

            return;
        }

        if (! $response->json('success')) {
            $fail('Verifikasi captcha gagal. Silakan coba lagi.');
        }
    }
}
