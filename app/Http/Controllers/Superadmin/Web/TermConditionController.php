<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebTermCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for "Syarat dan Ketentuan" (Terms & Conditions) entries
 * — same shape/pattern as Superadmin\Web\FaqController. The one thing
 * this controller adds on top of that shared pattern:
 *
 *   - Only one row is meant to be the "current" version shown on the
 *     register page (WebTermCondition::current()) — store()/update()
 *     deactivate every other row the moment one is saved as 'active',
 *     so a superadmin can't accidentally leave two rows both active with
 *     no defined "which one wins" behavior.
 *   - destroy() refuses to delete a version that at least one user's
 *     `terms_accepted_at` already points to (via users.terms_id) — with
 *     a clear reason, instead of surfacing the FK's raw
 *     restrictOnDelete() database error. See the users-table migration's
 *     docblock for why that acceptance record must never silently
 *     disappear.
 */
class TermConditionController extends Controller
{
    public function index(Request $request): View
    {
        $terms = CrudAdmin::getAll(
            modelClass: WebTermCondition::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'descriptions'],
        );

        return view('superadmin.web.term-conditions.index', compact('terms'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.term-conditions.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::store(WebTermCondition::class, $validated, afterCreate: function ($model) {
            $this->deactivateOtherVersions($model);
        });

        return redirect()
            ->route('web.term-conditions.index')
            ->with('success', 'Syarat & Ketentuan berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $term = CrudAdmin::find(WebTermCondition::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.term-conditions.edit', compact('term', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(WebTermCondition::class, $id, $validated, afterUpdate: function ($model) {
            $this->deactivateOtherVersions($model);
        });

        return redirect()
            ->route('web.term-conditions.index')
            ->with('success', 'Syarat & Ketentuan berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $acceptedByCount = User::where('terms_id', $id)->count();

        if ($acceptedByCount > 0) {
            return redirect()
                ->route('web.term-conditions.index')
                ->with('error', "Versi ini tidak bisa dihapus karena sudah disetujui oleh {$acceptedByCount} user — riwayat persetujuan wajib tetap tersimpan. Nonaktifkan saja (ubah status ke Inactive) jika versi ini sudah tidak berlaku.");
        }

        CrudAdmin::delete(WebTermCondition::class, $id);

        return redirect()
            ->route('web.term-conditions.index')
            ->with('success', 'Syarat & Ketentuan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'descriptions' => ['required', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function deactivateOtherVersions(WebTermCondition $current): void
    {
        if ($current->status !== 'active') {
            return;
        }

        WebTermCondition::where('id', '!=', $current->id)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);
    }
}
