<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * User yang dinonaktifkan (status != active) saat masih login langsung
 * dikeluarkan di request berikutnya -- tanpa ini sesi lama tetap bisa
 * dipakai sampai kedaluwarsa. Dipasang di grup 'web' (bootstrap/app.php).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->expectsJson()
                ? response()->json(['message' => 'Akun Anda tidak aktif.'], 401)
                : redirect()->route('login')->withErrors(['email' => 'Akun Anda tidak aktif. Hubungi admin untuk informasi lebih lanjut.']);
        }

        return $next($request);
    }
}
