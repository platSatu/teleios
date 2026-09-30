<?php

namespace App\Http\Controllers\User\Settings;

use App\Http\Controllers\Concerns\VerifiesTransactionPin;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 6-digit transaction PIN — wajib untuk setiap aksi yang memindahkan uang
 * (lihat Concerns\VerifiesTransactionPin).
 * Stored hashed on users.pin (cast 'hashed' in App\Models\User, same as
 * password). Setting it the first time needs no old PIN; changing an
 * existing one does, to stop someone with a hijacked session from
 * silently swapping in their own PIN.
 */
class PinController extends Controller
{
    use VerifiesTransactionPin;

    public function edit(): View
    {
        $hasPin = Auth::user()->hasTransactionPin();

        return view('user.settings.pin.edit', compact('hasPin'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $hasPin = $user->hasTransactionPin();

        $validated = $request->validate([
            'pin' => ['required', 'digits:6', 'confirmed'],
        ]);

        // Ganti PIN wajib PIN lama, dengan batas salah yang sama (gabungan)
        // seperti transaksi lain -- supaya PIN lama tidak bisa ditebak lewat sini.
        if ($hasPin && ($failed = $this->failedTransactionPin($request, 'current_pin'))) {
            return $failed;
        }

        $user->update(['pin' => $validated['pin']]);

        AuditLog::create([
            'actor_type' => $user::class,
            'actor_id' => $user->id,
            'action' => $hasPin ? 'PIN_CHANGE' : 'PIN_SET',
            'entity_type' => $user::class,
            'entity_id' => $user->id,
            'new_value' => ['pin_set' => true],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('user-settings.pin.edit')
            ->with('success', $hasPin ? 'PIN berhasil diubah.' : 'PIN berhasil dibuat. Sekarang Anda bisa transfer dan tarik saldo.');
    }
}
