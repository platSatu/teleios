<?php

namespace App\Http\Controllers\Superadmin\HelpCenters;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\CategoryHelpCenter;
use App\Models\HelpCenter;
use App\Models\HelpCenterAnswer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Superadmin side of the help-center ticket system: sees every ticket
 * across every user (unlike User\HelpCenters\HelpCenterController, which
 * is scoped to the logged-in user's own tickets via Crud::getByUser),
 * can edit/close/delete any of them, and can post into a ticket's reply
 * thread (help_center_answers) the same way the ticket's own user does —
 * see reply() below. All data access goes through CrudAdmin, same as
 * every other superadmin-only controller in this app.
 */
class HelpCenterController extends Controller
{
    public function index(Request $request): View
    {
        $helpCenters = CrudAdmin::getAll(
            modelClass: HelpCenter::class,
            relations: ['user', 'category'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['number_ticket', 'name'],
        );

        return view('superadmin.help-centers.index', compact('helpCenters'));
    }

    public function create(): View
    {
        $categories = CategoryHelpCenter::where('status', 'active')->orderBy('name')->get();
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.help-centers.create', compact('categories', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'category_help_centers_id' => ['required', 'uuid', 'exists:category_help_centers,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'status' => ['required', 'in:active,inactive,open,close'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg', 'max:5120'],
        ]);

        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('help-center-attachments', 'public');
        }

        $helpCenter = CrudAdmin::store(HelpCenter::class, $validated);

        return redirect()
            ->route('help-center.show', $helpCenter->id)
            ->with('success', 'Tiket help center berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $helpCenter = CrudAdmin::find(HelpCenter::class, $id, relations: ['user', 'category', 'answers.user']);

        return view('superadmin.help-centers.show', compact('helpCenter'));
    }

    public function edit(string $id): View
    {
        $helpCenter = CrudAdmin::find(HelpCenter::class, $id);
        $categories = CategoryHelpCenter::where('status', 'active')->orderBy('name')->get();

        return view('superadmin.help-centers.edit', compact('helpCenter', 'categories'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'category_help_centers_id' => ['required', 'uuid', 'exists:category_help_centers,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'status' => ['required', 'in:active,inactive,open,close'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg', 'max:5120'],
        ]);

        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('help-center-attachments', 'public');
        } else {
            unset($validated['attachment']);
        }

        CrudAdmin::update(
            HelpCenter::class,
            $id,
            $validated,
            beforeUpdate: function (HelpCenter $model, array $data) {
                // Closing a ticket stamps close_date; reopening it (back
                // to active/inactive/open) clears it — status and
                // close_date should never drift out of sync with each
                // other regardless of which direction the admin toggles.
                if ($data['status'] === 'close' && ! $model->close_date) {
                    $data['close_date'] = now();
                } elseif ($data['status'] !== 'close') {
                    $data['close_date'] = null;
                }

                // Replacing the attachment: delete the old file so
                // orphaned uploads don't pile up on disk.
                if (isset($data['attachment']) && $model->attachment) {
                    Storage::disk('public')->delete($model->attachment);
                }

                return $data;
            },
        );

        return redirect()
            ->route('help-center.show', $id)
            ->with('success', 'Tiket help center berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(
            HelpCenter::class,
            $id,
            beforeDelete: function (HelpCenter $model) {
                if ($model->attachment) {
                    Storage::disk('public')->delete($model->attachment);
                }
            },
        );

        return redirect()
            ->route('help-center.index')
            ->with('success', 'Tiket help center berhasil dihapus.');
    }

    /**
     * Posts one message into the ticket's reply thread as the logged-in
     * superadmin — same table (help_center_answers) the ticket's own
     * user posts into via User\HelpCenters\HelpCenterController::reply(),
     * just with user_id forced to the superadmin instead of the ticket
     * owner. Routed through CrudAdmin::store (not a bare ::create()) so
     * this still gets the superadmin guard + audit trail every other
     * superadmin write gets.
     */
    public function reply(Request $request, string $id): RedirectResponse
    {
        // Confirms the ticket exists (and applies the superadmin guard)
        // before accepting a reply against it.
        CrudAdmin::find(HelpCenter::class, $id);

        $validated = $request->validate([
            'answers' => ['required', 'string'],
        ]);

        CrudAdmin::store(HelpCenterAnswer::class, [
            'help_centers_id' => $id,
            'user_id' => Auth::id(),
            'answers' => $validated['answers'],
            'status' => 'active',
            'date_answers' => now(),
        ]);

        return redirect()
            ->route('help-center.show', $id)
            ->with('success', 'Balasan berhasil dikirim.');
    }
}
