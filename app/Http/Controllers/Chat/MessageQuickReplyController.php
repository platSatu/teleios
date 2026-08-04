<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WaMessageQuickReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for "Balasan Cepat" — canned responses an agent can insert into
 * the inbox's message box.
 */
class MessageQuickReplyController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $quickReplies = WaMessageQuickReply::where('company_id', $company->id)
            ->latest()
            ->paginate(15);

        return view('chat.message-quick-replies.index', compact('quickReplies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-quick-replies.index')
                ->withErrors($validator, 'newQuickReply')
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['company_id'] = $company->id;

        WaMessageQuickReply::create($validated);

        return redirect()
            ->route('chat.message-quick-replies.index')
            ->with('success', 'Balasan cepat berhasil dibuat.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $quickReply = WaMessageQuickReply::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-quick-replies.index')
                ->withErrors($validator, 'editQuickReply'.$id)
                ->withInput();
        }

        $quickReply->update($validator->validated());

        return redirect()
            ->route('chat.message-quick-replies.index')
            ->with('success', 'Balasan cepat berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $deleted = WaMessageQuickReply::where('company_id', $company->id)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            abort(404);
        }

        return redirect()
            ->route('chat.message-quick-replies.index')
            ->with('success', 'Balasan cepat berhasil dihapus.');
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'device_id' => ['required', 'string', 'max:36'],
            'title' => ['required', 'string', 'max:255'],
            'shortcut' => ['nullable', 'string', 'max:50'],
            'category' => ['required', 'in:text,location'],
            'message_content' => ['required', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

}
