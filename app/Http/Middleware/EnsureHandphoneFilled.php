<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * No. WhatsApp wajib, sama seperti form register. Pendaftar via Google
 * (Google tidak memberikan nomor HP) diarahkan ke halaman Profile dengan
 * pesan peringatan setiap kali membuka dashboard, sampai nomornya diisi.
 * Dipasang di grup 'web' (bootstrap/app.php); hanya berlaku untuk
 * halaman /dashboard, halaman Profile sendiri dikecualikan.
 */
class EnsureHandphoneFilled
{
    public const MESSAGE = 'Lengkapi nomor WhatsApp Anda terlebih dahulu sebelum menggunakan dashboard.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user
            || $user->user_type === 'SUPERADMIN'
            || filled($user->handphone)
            || ! $request->is('dashboard', 'dashboard/*')
            || $request->routeIs('profile.edit', 'profile.update')) {
            return $next($request);
        }

        return $request->expectsJson()
            ? response()->json(['message' => self::MESSAGE], 409)
            : redirect()->route('profile.edit')->with('warning', self::MESSAGE);
    }
}
