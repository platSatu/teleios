<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WaFormIntegration;
use App\Models\WaMessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for "Chat > Third Party > Google Form" — see
 * App\Models\WaFormIntegration's docblock for the full picture and
 * App\Http\Controllers\Api\GoogleFormWebhookController for how a
 * submission is actually turned into a WhatsApp reply.
 *
 * `type` is hardcoded to 'google_form' everywhere in this controller —
 * the table supports other integration types later, but this controller
 * only ever touches its own slice of it, same way
 * Chat\MessageTemplateController never has to think about
 * wa_category_templates rows belonging to some other feature.
 */
class GoogleFormIntegrationController extends Controller
{
    use ResolvesCompanyContext;

    private const TYPE = 'google_form';

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $integrations = WaFormIntegration::where('company_id', $company->id)
            ->where('type', self::TYPE)
            ->with('waMessageTemplate:id,name')
            ->withCount('submissions')
            ->latest()
            ->paginate(15)
            ->withQueryString()
            // Matches Pesan Terjadwal/Kontak's tighter pagination window —
            // see those controllers for why (default onEachSide=3 doesn't
            // start collapsing to "..." until 14+ pages).
            ->onEachSide(1);

        return view('chat.third-party.google-form.index', compact('integrations'));
    }

    public function create(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        return view('chat.third-party.google-form.create', [
            'integration' => null,
            'templates' => $this->templatesFor($company),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.third-party.google-form.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        WaFormIntegration::create([
            'company_id' => $company->id,
            'type' => self::TYPE,
            'name' => $validated['name'],
            'device_id' => $validated['device_id'],
            'wa_message_template_id' => $validated['wa_message_template_id'] ?: null,
            'target_number_field' => trim($validated['target_number_field']),
            'status' => $validated['status'] ?? 'active',
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('chat.third-party.google-form.index')
            ->with('success', 'Integrasi Google Form berhasil ditambahkan.');
    }

    public function show(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $integration = $this->findOrFail($company, $id);

        $submissions = $integration->submissions()->paginate(20);

        return view('chat.third-party.google-form.show', compact('integration', 'submissions'));
    }

    public function edit(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $integration = $this->findOrFail($company, $id);

        return view('chat.third-party.google-form.edit', [
            'integration' => $integration,
            'templates' => $this->templatesFor($company),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $integration = $this->findOrFail($company, $id);

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.third-party.google-form.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $integration->update([
            'name' => $validated['name'],
            'device_id' => $validated['device_id'],
            'wa_message_template_id' => $validated['wa_message_template_id'] ?: null,
            'target_number_field' => trim($validated['target_number_field']),
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('chat.third-party.google-form.index')
            ->with('success', 'Integrasi Google Form berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $integration = $this->findOrFail($company, $id);
        $integration->delete();

        return redirect()
            ->route('chat.third-party.google-form.index')
            ->with('success', 'Integrasi Google Form berhasil dihapus.');
    }

    /**
     * Old token stops accepting submissions the instant this saves — no
     * grace period, same behaviour as WaApiKey::regenerateToken(). Use
     * when a webhook URL may have leaked (e.g. pasted somewhere public).
     */
    public function regenerateToken(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $integration = $this->findOrFail($company, $id);
        $integration->regenerateWebhookToken();

        return redirect()
            ->route('chat.third-party.google-form.show', $id)
            ->with('success', 'Webhook URL baru berhasil dibuat. Perbarui juga script di Google Form kamu.');
    }

    /**
     * Every template the picker should offer: belongs to this company AND
     * currently sendable (WaMessageTemplate::scopeUsable — active status +
     * superadmin-approved review_status). Deliberately the SAME bar as
     * everywhere else a template is picked for an actual send (Pesan
     * Terjadwal, WA Template quick-send) — an in-review/rejected/draft
     * template should never be selectable as an auto-reply either.
     */
    private function templatesFor(Company $company)
    {
        return WaMessageTemplate::where('company_id', $company->id)
            ->usable()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function findOrFail(Company $company, string $id): WaFormIntegration
    {
        return WaFormIntegration::where('company_id', $company->id)
            ->where('type', self::TYPE)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function validator(Request $request, Company $company, ?string $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'device_id' => ['required', 'string', 'max:36'],
            'wa_message_template_id' => [
                'nullable', 'uuid',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! WaMessageTemplate::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('WA Template tidak valid.');
                    }
                },
            ],
            'target_number_field' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
