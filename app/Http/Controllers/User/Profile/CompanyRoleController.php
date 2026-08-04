<?php

namespace App\Http\Controllers\User\Profile;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Profile\Concerns\ScopesActivePackage;
use App\Models\Company;
use App\Models\CompanyRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD for the "Roles" tab on dashboard/user/profile. Always scoped to
 * the company owned by the logged-in user (Company::user_id === auth
 * id) — there's no route-supplied company id anywhere here, so a user
 * can never touch another company's roles.
 *
 * The "Owner" role is special-cased in destroy(): it's the row every
 * company is created with (see ProfileController::updateCompany()) and
 * is never deletable, regardless of whether it currently has members.
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

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        if ($this->activeCategoryApplicationIds($company->user_id)->isEmpty()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Anda belum memiliki package aktif. Beli package terlebih dahulu sebelum menambah role.');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'roles'])
                ->withErrors($validator, 'newRole')
                ->withInput();
        }

        $company->roles()->create($validator->validated());

        return redirect()
            ->route('profile.edit', ['tab' => 'roles'])
            ->with('success', 'Role berhasil ditambahkan.');
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
