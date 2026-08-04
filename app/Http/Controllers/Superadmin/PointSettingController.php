<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Superadmin control for the purchase cashback/point rule — "every
 * complete Rp X spent earns Rp Y back to the buyer's wallet", see
 * App\Models\Setting and Dashboard\PackageCheckoutController::
 * payPurchaseCashback(). Singleton settings (no id per row to manage),
 * so this is just edit()/update(), no full CRUD.
 */
class PointSettingController extends Controller
{
    public function edit(): View
    {
        $threshold = Setting::get('point_amount_threshold', 10000);
        $pointValue = Setting::get('point_value', 100);
        $enabled = filter_var(Setting::get('point_enabled', '1'), FILTER_VALIDATE_BOOLEAN);

        return view('superadmin.point-setting.edit', compact('threshold', 'pointValue', 'enabled'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'point_amount_threshold' => ['required', 'numeric', 'min:1'],
            'point_value' => ['required', 'numeric', 'min:0'],
            'point_enabled' => ['nullable', 'boolean'],
        ]);

        $before = [
            'point_amount_threshold' => Setting::get('point_amount_threshold'),
            'point_value' => Setting::get('point_value'),
            'point_enabled' => Setting::get('point_enabled'),
        ];

        Setting::set('point_amount_threshold', (string) $validated['point_amount_threshold']);
        Setting::set('point_value', (string) $validated['point_value']);
        Setting::set('point_enabled', $request->boolean('point_enabled') ? '1' : '0');

        AuditLog::create([
            'actor_type' => Auth::user() ? Auth::user()::class : null,
            'actor_id' => Auth::id(),
            'action' => 'point_setting.update',
            'entity_type' => Setting::class,
            'entity_id' => 'point_setting',
            'old_value' => $before,
            'new_value' => [
                'point_amount_threshold' => $validated['point_amount_threshold'],
                'point_value' => $validated['point_value'],
                'point_enabled' => $request->boolean('point_enabled'),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()
            ->route('point-setting.edit')
            ->with('success', 'Pengaturan point berhasil disimpan.');
    }
}
