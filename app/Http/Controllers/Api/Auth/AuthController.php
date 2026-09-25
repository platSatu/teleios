<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Login API (token Sanctum). Aturannya disamakan dengan login web
 * (Auth\AuthController): batas percobaan per email+IP, hanya akun aktif,
 * dan respon user minimal (tanpa kolom sensitif).
 */
class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    private const DUMMY_PASSWORD_HASH = '$2y$12$Nmt44A2ByUV5ogQ1687XQu.JT8Fzc7JyCM54N1M4jIehCwrs1554y';

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'api-login|'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json(['message' => 'Terlalu banyak percobaan login. Coba lagi nanti.'], 429);
        }

        $user = User::where('email', $credentials['email'])->first();
        $passwordValid = Hash::check($credentials['password'], $user?->password ?? self::DUMMY_PASSWORD_HASH);

        if (! $user || ! $passwordValid || $user->status !== 'active') {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return response()->json(['message' => 'Email atau password salah, atau akun belum aktif.'], 401);
        }

        RateLimiter::clear($key);

        return response()->json([
            'token' => $user->createToken('api-token')->plainTextToken,
            'user' => $user->only(['id', 'name', 'email']),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }
}
