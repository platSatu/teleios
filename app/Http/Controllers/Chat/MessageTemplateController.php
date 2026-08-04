<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WaMessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for "WA Template" (Chat > Pengaturan > Pesan > WA Template) — a
 * company's reusable WhatsApp message bodies, picked from the "Pesan
 * Terjadwal" form (see App\Http\Controllers\Chat\MessageScheduleController)
 * instead of retyping the same message every time. Always scoped to the
 * logged-in user's own company (ownedCompanyOrFail), same rule as every
 * other company-owned resource in this app — see
 * User\Profile\CompanyUserController for the original precedent.
 *
 * Full pages, not modals — same reasoning as company-users/create|edit:
 * a template body is a multi-line textarea, and a dedicated URL means
 * validation errors land on a page with only one form on it.
 */
class MessageTemplateController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $templates = WaMessageTemplate::where('company_id', $company->id)
            ->latest()
            ->paginate(15);

        return view('chat.message-templates.index', compact('templates'));
    }

    public function create(): View
    {
        return view('chat.message-templates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-templates.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['company_id'] = $company->id;

        WaMessageTemplate::create($validated);

        return redirect()
            ->route('chat.message-templates.index')
            ->with('success', 'Template WA berhasil dibuat.');
    }

    public function edit(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $template = WaMessageTemplate::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        return view('chat.message-templates.edit', compact('template'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $template = WaMessageTemplate::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-templates.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $template->update($validator->validated());

        return redirect()
            ->route('chat.message-templates.index')
            ->with('success', 'Template WA berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $deleted = WaMessageTemplate::where('company_id', $company->id)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            abort(404);
        }

        return redirect()
            ->route('chat.message-templates.index')
            ->with('success', 'Template WA berhasil dihapus.');
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'template' => ['required', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

}
