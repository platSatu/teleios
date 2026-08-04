<?php

namespace App\Http\Controllers\User\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * 6-digit transaction PIN — required before Dashboard\
 * WalletTransferController will let a user send a wallet transfer.
 * Stored hashed on users.pin (cast 'hashed' in App\Models\User, same as
 * password). Setting it the first time needs no old PIN; changing an
 * existing one does, to stop someone with a hijacked session from
 * silently swapping in their own PIN.
 */
class PinController extends Controller
{
    public function edit(): View
    {
        $hasPin = ! is_null(Auth::user()->pin);

        return view('user.settings.pin.edit', compact('hasPin'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $hasPin = ! is_null($user->pin);

        $rules = [
            'pin' => ['required', 'digits:6', 'confirmed'],
        ];

        if ($hasPin) {
            $rules['current_pin'] = ['required', 'digits:6'];
        }

        $validated = $request->validate($rules);

        if ($hasPin && ! Hash::check($validated['current_pin'], $user->pin)) {
            return back()->withErrors(['current_pin' => 'PIN saat ini salah.']);
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
            ->with('success', $hasPin ? 'PIN berhasil diubah.' : 'PIN berhasil dibuat. Anda sekarang bisa transfer saldo.');
    }
}
