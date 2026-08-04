<?php

namespace App\Http\Controllers\User\HelpCenters;

use App\Helpers\Crud;
use App\Http\Controllers\Controller;
use App\Models\CategoryHelpCenter;
use App\Models\HelpCenter;
use App\Models\HelpCenterAnswer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * User-facing side of the help-center ticket system: a logged-in user
 * files a complaint/question (store()), sees only their OWN tickets
 * (index()/show()), and can post into a ticket's reply thread
 * (reply()) — the same help_center_answers table
 * Superadmin\HelpCenters\HelpCenterController::reply() posts into, just
 * with user_id forced to the ticket's own owner instead of a superadmin.
 *
 * Deliberately does NOT go through Crud:: at all for reads here — not
 * getByUser() (index) or getById() (show): both hard-code an
 * `orWhere('company_id', ...)` ownership branch whenever the logged-in
 * user happens to have a company_id set, and help_centers has no
 * company_id column at all (that branch would throw an "unknown column"
 * SQL error rather than just being a no-op), and index() additionally
 * needs a `status != close` filter Crud::getByUser() has no way to
 * express. Plain Eloquent queries scoped to Auth::id() (same pattern
 * already used by User\Deposit\DepositController) sidestep both
 * problems. Crud::store() (create/reply, forces user_id) is still used
 * for writes, since that one never touches company_id in the first
 * place.
 */
class HelpCenterController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->value() ?: null;

        $query = HelpCenter::with(['category'])
            ->where('user_id', Auth::id())
            // A closed ticket is done — it drops off the user's own
            // list once resolved (still visible to a superadmin, and
            // still reachable directly via show() if the user kept the
            // link/number), same idea as an inbox archiving a resolved
            // thread instead of deleting it outright.
            ->where('status', '!=', 'close')
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('number_ticket', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $helpCenters = $query->paginate(10)->withQueryString();

        $categories = CategoryHelpCenter::where('status', 'active')->orderBy('name')->get();

        return view('user.help-center.index', compact('helpCenters', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_help_centers_id' => ['required', 'uuid', 'exists:category_help_centers,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg', 'max:5120'],
        ]);

        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('help-center-attachments', 'public');
        }

        // status/open_date/number_ticket are deliberately left unset —
        // HelpCenter's own creating() hook fills all three in with the
        // right defaults (see app/Models/HelpCenter.php), so a
        // self-service ticket always starts life exactly like a
        // superadmin-created one.
        $helpCenter = Crud::store(HelpCenter::class, $validated);

        return redirect()
            ->route('user-help-center.show', $helpCenter->id)
            ->with('success', 'Tiket berhasil dibuat. Nomor tiket: '.$helpCenter->number_ticket);
    }

    public function show(string $id): View
    {
        $helpCenter = HelpCenter::with(['category', 'answers.user'])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        return view('user.help-center.show', compact('helpCenter'));
    }

    public function reply(Request $request, string $id): RedirectResponse
    {
        $helpCenter = HelpCenter::where('user_id', Auth::id())->findOrFail($id);

        $validated = $request->validate([
            'answers' => ['required', 'string'],
        ]);

        Crud::store(HelpCenterAnswer::class, [
            'help_centers_id' => $helpCenter->id,
            'answers' => $validated['answers'],
        ]);

        return redirect()
            ->route('user-help-center.show', $helpCenter->id)
            ->with('success', 'Balasan berhasil dikirim.');
    }
}
