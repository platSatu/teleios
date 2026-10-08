<?php

namespace App\Http\Middleware;

use App\Models\PgMerchant;
use App\Services\PackageLimitService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Payment Gateway (server website pemakai -> teleios). Wajib:
 *   X-Pay-Key        : api_key merchant
 *   X-Pay-Timestamp  : unix detik, maksimal selisih 5 menit (anti replay)
 *   X-Pay-Signature  : HMAC-SHA256(secret, timestamp + "." + raw body)
 * Merchant harus aktif & company-nya punya paket "Payment Gateway".
 * Rate limit per merchant (Redis), default 600 request/menit.
 */
class VerifyPgSignature
{
    public function __construct(private PackageLimitService $packages)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Selalu balas JSON (validasi/404), walau server pemakai tidak kirim Accept.
        $request->headers->set('Accept', 'application/json');

        $key = (string) $request->header('X-Pay-Key');
        $timestamp = (string) $request->header('X-Pay-Timestamp');
        $signature = (string) $request->header('X-Pay-Signature');

        if ($key === '' || ! ctype_digit($timestamp) || $signature === '') {
            return $this->error('Header X-Pay-Key, X-Pay-Timestamp, dan X-Pay-Signature wajib diisi.', 401);
        }

        if (abs(now()->timestamp - (int) $timestamp) > 300) {
            return $this->error('X-Pay-Timestamp kedaluwarsa. Pastikan jam server Anda tepat.', 401);
        }

        $merchant = PgMerchant::with('company')->where('api_key', $key)->first();

        if (! $merchant || ! hash_equals($merchant->sign($timestamp, $request->getContent()), $signature)) {
            return $this->error('Signature tidak valid.', 401);
        }

        if (! $merchant->isActive()) {
            return $this->error('Akun Payment Gateway belum aktif atau sedang dinonaktifkan.', 403);
        }

        $hasPackage = Cache::remember("pg:package:{$merchant->company_id}", 60, fn () => $merchant->company
            && $this->packages->hasActiveCategoryPackage($merchant->company, ['Payment Gateway']));

        if (! $hasPackage) {
            return $this->error('Paket Payment Gateway tidak aktif.', 403);
        }

        $limiterKey = "pg:rate:{$merchant->id}";

        if (RateLimiter::tooManyAttempts($limiterKey, max(1, $merchant->rate_limit_per_minute))) {
            return $this->error('Terlalu banyak request. Coba lagi sebentar.', 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($limiterKey));
        }

        RateLimiter::hit($limiterKey, 60);

        $request->attributes->set('pgMerchant', $merchant);

        return $next($request);
    }

    private function error(string $message, int $status)
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }
}
