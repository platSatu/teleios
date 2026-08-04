<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\WaMessageAutoReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for keyword-triggered auto replies: when an incoming message
 * matches `keyword` (per match_type), `reply_message` is sent back — see
 * App\Http\Controllers\Api\WaIncomingMessageWebhookController.
 *
 * At most one rule per device_id can have `is_default` set — it's the
 * fallback sent when nothing matches (see the webhook controller), so
 * having two would make "which one fires" ambiguous. store()/update()
 * enforce that by unsetting any other default on the same device the
 * moment a new one is saved, rather than validating it as an error the
 * user has to manually resolve first.
 */
class MessageAutoReplyController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $autoReplies = WaMessageAutoReply::where('company_id', $company->id)
            ->latest()
            ->paginate(15);

        return view('chat.message-auto-replies.index', compact('autoReplies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-auto-replies.index')
                ->withErrors($validator, 'newAutoReply')
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['company_id'] = $company->id;
        $validated['is_default'] = $request->boolean('is_default');

        // A default rule doesn't match on a keyword — keep it null
        // rather than saving stray keyword/match_type input left over
        // from before the "Jadikan default" toggle was switched on.
        if ($validated['is_default']) {
            $validated['keyword'] = null;
        }

        $autoReply = WaMessageAutoReply::create($validated);

        $this->demoteOtherDefaults($company, $autoReply);

        return redirect()
            ->route('chat.message-auto-replies.index')
            ->with('success', 'Auto reply berhasil dibuat.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $autoReply = WaMessageAutoReply::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('chat.message-auto-replies.index')
                ->withErrors($validator, 'editAutoReply'.$id)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['is_default'] = $request->boolean('is_default');

        if ($validated['is_default']) {
            $validated['keyword'] = null;
        }

        $autoReply->update($validated);

        $this->demoteOtherDefaults($company, $autoReply);

        return redirect()
            ->route('chat.message-auto-replies.index')
            ->with('success', 'Auto reply berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $deleted = WaMessageAutoReply::where('company_id', $company->id)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            abort(404);
        }

        return redirect()
            ->route('chat.message-auto-replies.index')
            ->with('success', 'Auto reply berhasil dihapus.');
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'device_id' => ['required', 'string', 'max:36'],
            'is_default' => ['nullable', 'boolean'],
            // Only required for a non-default rule — a default rule has
            // no keyword of its own (enforced in store()/update() above,
            // not here, since $request->boolean('is_default') needs to
            // be read the same way both places).
            'keyword' => ['nullable', 'required_if:is_default,0', 'string', 'max:255'],
            'match_type' => ['required', 'in:contains,exact'],
            'reply_message' => ['required', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    /**
     * Keeps `is_default` unique per device_id: whichever rule was just
     * saved as the default wins, every other rule on that same device
     * gets demoted. Runs after save (not as a validation error) so
     * switching which rule is the default is a single click, not a
     * two-step "turn the old one off first" dance.
     */
    private function demoteOtherDefaults(Company $company, WaMessageAutoReply $justSaved): void
    {
        if (! $justSaved->is_default) {
            return;
        }

        WaMessageAutoReply::where('company_id', $company->id)
            ->where('device_id', $justSaved->device_id)
            ->where('id', '!=', $justSaved->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

}
