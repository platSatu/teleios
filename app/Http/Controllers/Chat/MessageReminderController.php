<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WaMessageReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for "Pengingat" — a one-off reminder message sent at
 * start_reminder, to either a phone_number or a WhatsApp group.
 */
class MessageReminderController extends Controller
{
    use ResolvesCompanyContext;

    /**
     * Fixed set for now — the useful category list is a business
     * decision the company owner should eventually be able to manage
     * themselves, but that's a separate follow-up, not built here.
     */
    private const CATEGORIES = ['Pembayaran', 'Follow Up', 'Umum', 'Lainnya'];

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $reminders = WaMessageReminder::where('company_id', $company->id)
            ->latest()
            ->paginate(15);

        $categories = self::CATEGORIES;

        return view('chat.message-reminders.index', compact('reminders', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-reminders.index')
                ->withErrors($validator, 'newReminder')
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['company_id'] = $company->id;
        $validated['is_group'] = $request->boolean('is_group');

        WaMessageReminder::create($validated);

        return redirect()
            ->route('chat.message-reminders.index')
            ->with('success', 'Pengingat berhasil dibuat.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $reminder = WaMessageReminder::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-reminders.index')
                ->withErrors($validator, 'editReminder'.$id)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['is_group'] = $request->boolean('is_group');

        $reminder->update($validated);

        return redirect()
            ->route('chat.message-reminders.index')
            ->with('success', 'Pengingat berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $deleted = WaMessageReminder::where('company_id', $company->id)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            abort(404);
        }

        return redirect()
            ->route('chat.message-reminders.index')
            ->with('success', 'Pengingat berhasil dihapus.');
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'device_id' => ['required', 'string', 'max:36'],
            'category_message_reminder' => ['required', 'string', 'max:50'],
            'title_reminder' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'start_reminder' => ['required', 'date'],
            'is_group' => ['nullable', 'boolean'],
            'group_jid' => ['required_if:is_group,1', 'nullable', 'string', 'max:255'],
            'phone_number' => ['required_unless:is_group,1', 'nullable', 'string', 'max:32'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

}
