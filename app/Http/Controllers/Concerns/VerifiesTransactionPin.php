<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Satu-satunya tempat pengecekan PIN transaksi (users.pin, hashed) untuk
 * semua aksi yang memindahkan uang: Transfer Saldo, ajukan & setujui
 * Tarik Saldo, Transfer Fee pengajar, dan ganti PIN itu sendiri.
 *
 * Batas salah PIN dihitung GABUNGAN per user (bukan per fitur), jadi
 * sesi yang dibajak tetap hanya punya 5 tebakan per 15 menit di seluruh
 * aplikasi, bukan 5 tebakan di setiap halaman.
 *
 * Pemakaian:
 *   if ($failed = $this->failedTransactionPin($request)) {
 *       return $failed;
 *   }
 */
trait VerifiesTransactionPin
{
    private int $maxPinAttempts = 5;

    private int $pinLockoutSeconds = 900;

    /** null = PIN benar; selain itu response yang harus langsung dikembalikan. */
    protected function failedTransactionPin(Request $request, string $field = 'pin'): ?RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTransactionPin()) {
            return redirect()
                ->route('user-settings.pin.edit')
                ->with('error', 'Anda belum membuat PIN Transaksi. Buat dulu PIN 6 digit untuk melanjutkan.');
        }

        $pin = $request->validate([$field => ['required', 'digits:6']], [
            $field.'.required' => 'Masukkan PIN Transaksi Anda.',
            $field.'.digits' => 'PIN Transaksi harus 6 digit angka.',
        ])[$field];

        $key = 'transaction-pin:'.$user->id;
        $back = back()->withInput($request->except(['pin', 'current_pin', 'pin_confirmation']));

        if (RateLimiter::tooManyAttempts($key, $this->maxPinAttempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            return $back->with('error', "Terlalu banyak percobaan PIN yang salah. Coba lagi dalam {$minutes} menit.");
        }

        if (! Hash::check($pin, $user->pin)) {
            RateLimiter::hit($key, $this->pinLockoutSeconds);
            $left = RateLimiter::retriesLeft($key, $this->maxPinAttempts);

            return $back->with('error', $left > 0
                ? "PIN salah. Sisa percobaan: {$left} kali."
                : 'Terlalu banyak percobaan PIN yang salah. Coba lagi dalam '.intdiv($this->pinLockoutSeconds, 60).' menit.');
        }

        RateLimiter::clear($key);

        return null;
    }
}
