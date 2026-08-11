<?php

namespace App\Http\Controllers\User\Profile;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Profile\Concerns\ScopesActivePackage;
use App\Models\BranchOfficeUnit;
use App\Models\Company;
use App\Models\CompanyRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for the "Roles" tab on dashboard/user/profile. Always scoped to
 * the company owned by the logged-in user (Company::user_id === auth
 * id) — there's no route-supplied company id anywhere here, so a user
 * can never touch another company's roles.
 *
 * The "Owner" role is special-cased in destroy(): it's the row every
 * company is created with (see ProfileController::updateCompany()) and
 * is never deletable, regardless of whether it currently has members. It
 * also has no branch_office_unit_id (company-wide) — the only role that
 * gets to be exempt from the "locked to one Division" rule below, since
 * it already existed before that rule was introduced.
 *
 * Every NEW role is created via create()/store() below, reached from a
 * unit's "Add Role" row action, and is always locked to that one
 * Division — see migration
 * 2026_08_11_100000_add_branch_office_unit_id_to_company_roles_table's
 * docblock for why roles aren't reusable across several divisions.
 *
 * Creating NEW roles additionally requires the owner to currently have
 * at least one active package (see ScopesActivePackage) — the "Roles"
 * tab is hidden in the view when that's not the case. Editing/deleting
 * an already-existing role (including the seeded "Owner" role, which
 * exists before any package purchase) stays allowed regardless, so an
 * owner whose package has since lapsed can still fix a typo or retire a
 * role without being locked out of managing what they already have.
 */
class CompanyRoleController extends Controller
{
    use ResolvesCompanyContext;

    use ScopesActivePackage;

    /**
     * "Add Role" row action on the Unit/Divisi tab — dedicated page,
     * Company/Branch/Unit shown read-only, same pattern as
     * BranchOfficeUnitController::create(). Also sets `active_company_id`
     * for this browsing session, same reasoning as that method.
     */
    public function create(Request $request, string $unitId): View
    {
        $unit = BranchOfficeUnit::whereHas('branchOffice.company', function ($q) {
            $q->where('user_id', Auth::id());
        })->with('branchOffice.company')->findOrFail($unitId);

        $branchOffice = $unit->branchOffice;
        $company = $branchOffice->company;

        if ($this->activeCategoryApplicationIds($company->user_id)->isEmpty()) {
            abort(403, 'Anda belum memiliki package aktif.');
        }

        session(['active_company_id' => $company->id]);

        return view('user.profile.company-roles.create', compact('company', 'branchOffice', 'unit'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        if ($this->activeCategoryApplicationIds($company->user_id)->isEmpty()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Anda belum memiliki package aktif. Beli package terlebih dahulu sebelum menambah role.');
        }

        $validator = Validator::make($request->all(), [
            'branch_office_unit_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $validator->after(function ($validator) use ($request, $company) {
            if ($validator->errors()->has('branch_office_unit_id')) {
                return;
            }

            $unit = BranchOfficeUnit::whereHas('branchOffice', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })->find($request->input('branch_office_unit_id'));

            if (! $unit) {
                $validator->errors()->add('branch_office_unit_id', 'Unit/Divisi tidak valid.');
            }
        });

        if ($validator->fails()) {
            return redirect()
                ->route('profile.company-roles.create', $request->input('branch_office_unit_id'))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $unit = BranchOfficeUnit::findOrFail($validated['branch_office_unit_id']);

        $company->roles()->create([
            'branch_office_id' => $unit->branch_office_id,
            'branch_office_unit_id' => $unit->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('profile.edit', ['tab' => 'roles'])
            ->with('success', 'Role berhasil ditambahkan.');
    }

    public function show(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $role = CompanyRole::where('company_id', $company->id)
            ->with('branchOfficeUnit.branchOffice')
            ->withCount('roleMenus')
            ->findOrFail($id);

        $branchOffice = $role->branchOfficeUnit?->branchOffice;
        $unit = $role->branchOfficeUnit;

        return view('user.profile.company-roles.show', compact('company', 'branchOffice', 'unit', 'role'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $role = CompanyRole::where('company_id', $company->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'roles'])
                ->withErrors($validator, 'editRole' . $role->id)
                ->withInput();
        }

        $role->update($validator->validated());

        return redirect()
            ->route('profile.edit', ['tab' => 'roles'])
            ->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $role = CompanyRole::where('company_id', $company->id)->findOrFail($id);

        if (strcasecmp($role->name, 'Owner') === 0) {
            return redirect()
                ->route('profile.edit', ['tab' => 'roles'])
                ->with('error', 'Role Owner tidak dapat dihapus.');
        }

        if ($role->members()->exists()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'roles'])
                ->with('error', 'Role masih dipakai oleh user. Pindahkan usernya ke role lain dulu.');
        }

        $role->delete();

        return redirect()
            ->route('profile.edit', ['tab' => 'roles'])
            ->with('success', 'Role berhasil dihapus.');
    }

}
