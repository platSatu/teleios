<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan standar untuk semua halaman web:
 * - X-Frame-Options: halaman tidak bisa di-embed situs lain (clickjacking),
 *   kecuali form publik (/{slug}) yang memang boleh di-embed.
 * - X-Content-Type-Options: browser tidak menebak tipe file.
 * - Referrer-Policy: URL lengkap (token di link) tidak bocor ke situs lain.
 * - Permissions-Policy: kamera/mikrofon/lokasi mati secara default.
 * - HSTS: hanya untuk request HTTPS, paksa HTTPS 1 tahun.
 * Header yang sudah diset di tempat lain (mis. nginx) tidak ditimpa.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        // Form publik boleh di-embed pelanggan di website mereka (iframe).
        if (! $request->routeIs('form.public.*')) {
            $headers['X-Frame-Options'] = 'SAMEORIGIN';
        }

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
