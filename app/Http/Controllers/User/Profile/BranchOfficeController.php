<?php

namespace App\Http\Controllers\User\Profile;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Profile\Concerns\ScopesActivePackage;
use App\Models\BranchOffice;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * CRUD for the "Branch Office" tab on dashboard/user/profile. Always
 * scoped to the company owned by the logged-in user (Company::user_id
 * === auth id), same rule as CompanyRoleController — there's no
 * route-supplied company id anywhere here.
 *
 * Sits between "Company" and "Unit/Divisi" in the onboarding flow: a
 * company must exist before a branch office can be created, and a
 * branch office must exist before a unit can be created under it (see
 * BranchOfficeUnitController). The view hides this tab's add form
 * behind that same "no company yet" check the other tabs use.
 *
 * Creating NEW branch offices additionally requires the owner to
 * currently have at least one active package (see ScopesActivePackage)
 * — the tab itself is hidden in the view when that's not the case, same
 * gating as Setting Users/Roles/Applications. Editing/deleting an
 * already-existing branch office stays allowed regardless (same
 * "manage what you already have, even after a package lapses" stance as
 * CompanyRoleController), even though the view currently has no path to
 * reach those actions once the tab is hidden.
 */
class BranchOfficeController extends Controller
{
    use ResolvesCompanyContext;

    use ScopesActivePackage;

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        if ($this->activeCategoryApplicationIds($company->user_id)->isEmpty()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Anda belum memiliki package aktif. Beli package terlebih dahulu sebelum menambah branch office.');
        }

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'branch-office'])
                ->withErrors($validator, 'newBranchOffice')
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        $company->branchOffices()->create($validated);

        return redirect()
            ->route('profile.edit', ['tab' => 'branch-office'])
            ->with('success', 'Branch office berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $branchOffice = BranchOffice::where('company_id', $company->id)->findOrFail($id);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'branch-office'])
                ->withErrors($validator, 'editBranchOffice' . $branchOffice->id)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['slug'] = $this->uniqueSlug($validated['name'], $branchOffice->id);

        $branchOffice->update($validated);

        return redirect()
            ->route('profile.edit', ['tab' => 'branch-office'])
            ->with('success', 'Branch office berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $branchOffice = BranchOffice::where('company_id', $company->id)->findOrFail($id);

        if ($branchOffice->units()->exists()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'branch-office'])
                ->with('error', 'Branch office masih punya unit/divisi. Hapus unitnya dulu.');
        }

        $branchOffice->delete();

        return redirect()
            ->route('profile.edit', ['tab' => 'branch-office'])
            ->with('success', 'Branch office berhasil dihapus.');
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    /**
     * Str::slug($name), re-rolled with a numeric suffix (-2, -3, ...)
     * until it doesn't collide with another branch office's slug —
     * same approach as Company::slug (see ProfileController::
     * uniqueSlug()). $ignoreId excludes the record being updated.
     */
    private function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'branch-office';
        $slug = $base;
        $suffix = 2;

        while (
            BranchOffice::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

}
