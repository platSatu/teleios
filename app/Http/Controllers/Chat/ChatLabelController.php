<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\WaChatLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for the "Label" catalog (Chat > Pengaturan > Label) — the small
 * set of labels (e.g. "Prospek", "VIP", "Sudah Bayar") a company can tag
 * onto individual chats from the Inbox detail panel. This controller
 * only manages the catalog itself; tagging a specific chat with a label
 * is a separate, lighter endpoint on InboxController (labels()/
 * attachLabel()/detachLabel()), since that's scoped to one device+chat
 * rather than the whole company.
 *
 * Single page, modal-based create/edit (like Superadmin's AI Bot
 * provider/model catalogs) — a label is just a name + a color, doesn't
 * warrant its own dedicated create/edit URLs.
 */
class ChatLabelController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $labels = WaChatLabel::where('company_id', $company->id)
            ->orderBy('name')
            ->get();

        return view('chat.labels.index', compact('labels'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.labels.index')
                ->withErrors($validator, 'newLabel')
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['company_id'] = $company->id;

        WaChatLabel::create($validated);

        return redirect()
            ->route('chat.labels.index')
            ->with('success', 'Label berhasil dibuat.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $label = WaChatLabel::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.labels.index')
                ->withErrors($validator, 'editLabel'.$id)
                ->withInput();
        }

        $label->update($validator->validated());

        return redirect()
            ->route('chat.labels.index')
            ->with('success', 'Label berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        // Deleting the catalog row cascades to wa_chat_label_assignments
        // (see the migration's ->cascadeOnDelete()) — any chat tagged
        // with this label just quietly loses that tag, nothing else to
        // clean up here.
        $deleted = WaChatLabel::where('company_id', $company->id)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            abort(404);
        }

        return redirect()
            ->route('chat.labels.index')
            ->with('success', 'Label berhasil dihapus.');
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }
}
