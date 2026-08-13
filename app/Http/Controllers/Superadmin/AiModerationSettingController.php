<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\AiModerationSetting;
use App\Models\WaAiBotProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Singleton settings screen for App\Models\AiModerationSetting — "1
 * fungsi" the superadmin uses to plant an AI provider's API key
 * (ChatGPT/Gemini/Claude/DeepSeek, via the shared App\Models\
 * WaAiBotProvider/WaAiBotModel catalog) and pick which content
 * categories it should block, for App\Services\Moderation\
 * TemplateModerationService to actually run against every company's
 * Kategori Template and WA Template.
 *
 * Just edit()/update() — no index/create/destroy, since there is
 * exactly one row (see AiModerationSetting::current()). Writes still go
 * through CrudAdmin::update() for the same audit-log trail every other
 * superadmin write gets, even though there's no per-row list to browse.
 */
class AiModerationSettingController extends Controller
{
    public function edit(): View
    {
        $setting = AiModerationSetting::current();

        $providers = WaAiBotProvider::where('status', 'active')
            ->with(['models' => fn ($q) => $q->where('status', 'active')->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('superadmin.ai-moderation-setting.edit', compact('setting', 'providers'));
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = AiModerationSetting::current();

        $validated = $request->validate([
            'wa_ai_bot_provider_id' => ['nullable', 'uuid', 'exists:wa_ai_bot_providers,id'],
            'wa_ai_bot_model_id' => ['nullable', 'uuid', 'exists:wa_ai_bot_models,id'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'blocked_keywords' => ['nullable', 'string', 'max:2000'],
            'custom_instructions' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data = [
            'wa_ai_bot_provider_id' => $validated['wa_ai_bot_provider_id'] ?? null,
            'wa_ai_bot_model_id' => $validated['wa_ai_bot_model_id'] ?? null,
            'block_pornography' => $request->boolean('block_pornography'),
            'block_gambling' => $request->boolean('block_gambling'),
            'block_drugs' => $request->boolean('block_drugs'),
            'block_negative_language' => $request->boolean('block_negative_language'),
            'blocked_keywords' => $validated['blocked_keywords'] ?? null,
            'custom_instructions' => $validated['custom_instructions'] ?? null,
            'status' => $validated['status'],
            'updated_by' => $request->user()->id,
        ];

        // The form never echoes the real key back (see the view) — a
        // blank submit means "leave the existing key alone", not "erase
        // it". Only overwrite when the superadmin actually typed a new
        // one.
        if (! empty($validated['api_key'])) {
            $data['api_key'] = $validated['api_key'];
        }

        CrudAdmin::update(AiModerationSetting::class, $setting->id, $data);

        return redirect()
            ->route('ai-moderation-setting.edit')
            ->with('success', 'Pengaturan moderasi AI berhasil disimpan.');
    }
}
