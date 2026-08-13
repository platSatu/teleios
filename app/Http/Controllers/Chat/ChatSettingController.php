<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A single settings page for the handful of company-level Chat config
 * values that previously had a database column (added across Fitur #1,
 * #2 and #7) but no form to actually edit them from: SLA minutes
 * (App\Services\Chat\ConversationService), broadcast throttle
 * (App\Services\Chat\BroadcastThrottleService), and the CSAT survey
 * toggle/question (App\Services\Chat\CsatSurveyService). Grouped on one
 * page rather than scattered across each feature's own screen since
 * they're all "set once, rarely touched again" company-wide defaults,
 * not something managed per-conversation/per-broadcast.
 */
class ChatSettingController extends Controller
{
    use ResolvesCompanyContext;

    public function edit(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        return view('chat.settings.edit', ['company' => $company]);
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validated = $request->validate([
            'chat_sla_first_response_minutes' => ['nullable', 'integer', 'min:1', 'max:10080'],
            'chat_sla_resolution_minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'chat_broadcast_max_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'csat_enabled' => ['nullable', 'boolean'],
            'csat_question' => ['nullable', 'string', 'max:255'],
        ]);

        $validated['csat_enabled'] = $request->boolean('csat_enabled');

        $company->update($validated);

        return redirect()
            ->route('chat.settings.edit')
            ->with('success', 'Pengaturan Chat berhasil disimpan.');
    }
}
