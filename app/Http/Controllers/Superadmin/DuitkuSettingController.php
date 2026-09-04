<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\DuitkuSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Singleton settings screen for App\Models\DuitkuSetting — where the
 * Duitku merchant credentials that used to live in .env now get edited
 * (Merchant Code + API Key, separately for sandbox and production) plus
 * which of the two is currently active. See DuitkuSetting's migration
 * docblock for the full design rationale.
 *
 * Just edit()/update() — no index/create/destroy, since there is exactly
 * one row (see DuitkuSetting::current()) — same shape as
 * Superadmin\AiModerationSettingController. Writes go through
 * CrudAdmin::update() for the same audit-log trail every other
 * superadmin write gets.
 */
class DuitkuSettingController extends Controller
{
    public function edit(): View
    {
        $setting = DuitkuSetting::current();

        return view('superadmin.duitku-setting.edit', compact('setting'));
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = DuitkuSetting::current();

        $validated = $request->validate([
            'mode' => ['required', Rule::in(DuitkuSetting::MODES)],
            'sandbox_merchant_code' => ['nullable', 'string', 'max:100'],
            'sandbox_api_key' => ['nullable', 'string', 'max:500'],
            'production_merchant_code' => ['nullable', 'string', 'max:100'],
            'production_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $data = [
            'mode' => $validated['mode'],
            'sandbox_merchant_code' => $validated['sandbox_merchant_code'] ?? null,
            'production_merchant_code' => $validated['production_merchant_code'] ?? null,
            'updated_by' => $request->user()->id,
        ];

        // Sama seperti AiModerationSettingController::update() — form
        // TIDAK PERNAH echo API key asli balik ke input (lihat view),
        // jadi field kosong berarti "biarkan key yang sudah tersimpan",
        // bukan "hapus key-nya". Cuma di-overwrite kalau superadmin
        // benar-benar mengetik key baru.
        if (! empty($validated['sandbox_api_key'])) {
            $data['sandbox_api_key'] = $validated['sandbox_api_key'];
        }

        if (! empty($validated['production_api_key'])) {
            $data['production_api_key'] = $validated['production_api_key'];
        }

        // Dicek terhadap nilai HASIL AKHIR (bukan input mentah) supaya
        // pola "kosongkan = pertahankan key lama" di atas ikut terhitung
        // — superadmin baru boleh pindah ke sebuah mode kalau mode itu
        // (setelah update ini tersimpan) beneran punya merchant code +
        // api key, bukannya baru ketahuan gagal nanti pas ada orang
        // top-up (lewat DuitkuService::make()'s RuntimeException).
        $merchantCodeKey = $validated['mode'] === DuitkuSetting::MODE_SANDBOX
            ? 'sandbox_merchant_code'
            : 'production_merchant_code';
        $apiKeyKey = $validated['mode'] === DuitkuSetting::MODE_SANDBOX
            ? 'sandbox_api_key'
            : 'production_api_key';

        $resolvedMerchantCode = $data[$merchantCodeKey] ?? null;
        $resolvedApiKey = $data[$apiKeyKey] ?? $setting->{$apiKeyKey};

        if (blank($resolvedMerchantCode) || blank($resolvedApiKey)) {
            $modeLabel = $validated['mode'] === DuitkuSetting::MODE_SANDBOX ? 'Sandbox' : 'Production';

            return back()
                ->withInput()
                ->withErrors(['mode' => "Merchant Code dan API Key {$modeLabel} harus diisi dulu sebelum mode ini bisa diaktifkan."]);
        }

        CrudAdmin::update(DuitkuSetting::class, $setting->id, $data);

        return redirect()
            ->route('duitku-setting.edit')
            ->with('success', 'Pengaturan Duitku berhasil disimpan.');
    }
}
