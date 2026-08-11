<?php

namespace App\Http\Controllers\User\Profile;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Profile\Concerns\ScopesActivePackage;
use App\Models\ApplicationMenu;
use App\Models\CategoryApplication;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\CompanyRoleMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * CRUD for the "Applications" tab on dashboard/user/profile — which
 * Application Menu entries (superadmin's catalog, see App\Models\
 * ApplicationMenu) each App\Models\CompanyRole in the logged-in user's
 * company can see. Same scoping rule as CompanyRoleController/
 * CompanyUserController: always Company::user_id === auth id, never a
 * route-supplied company id.
 *
 * Per-ROLE, not company-wide, since App\Models\CompanyRoleMenu gained a
 * `company_role_id` column (see migration
 * 2026_08_03_170000_add_company_role_id_to_company_role_menus_table) —
 * before that, switching a menu "on" applied identically to every
 * member of the company regardless of their role, which is what made
 * the sidebar (resources/views/layouts/partials/menu.blade.php) unable
 * to actually differ per role. Uniqueness is now per
 * (company_role_id, application_menu_id) instead of just
 * application_menu_id — the SAME menu can be switched on for one role
 * and off for another.
 *
 * Superadmin has an unscoped, cross-company version of this same CRUD
 * (Superadmin\CompanyRoleMenuController) for troubleshooting when a
 * company reports a missing/wrong menu.
 *
 * Switching on a NEW menu additionally requires the owner to currently
 * have at least one active package (see ScopesActivePackage), and
 * category_application_id is restricted to ONLY the categories covered
 * by an active package — the "Applications" tab is hidden in the view
 * when there's no active package, but this is the server-side backstop
 * so a menu for an unpaid category can't be switched on by posting to
 * the route directly. update()/destroy() (status toggle / removal of an
 * already-existing menu) stay unrestricted, same reasoning as
 * CompanyRoleController.
 */
class CompanyRoleMenuController extends Controller
{
    use ResolvesCompanyContext;

    use ScopesActivePackage;

    /**
     * "Add Application" row action on the Roles tab — dedicated page,
     * Company/Branch/Unit/Role shown read-only, same pattern as
     * CompanyRoleController::create(). Also sets `active_company_id` for
     * this browsing session, same reasoning as that method.
     */
    public function create(Request $request, string $roleId): View
    {
        $role = CompanyRole::whereHas('company', function ($q) {
            $q->where('user_id', Auth::id());
        })->with(['company', 'branchOfficeUnit.branchOffice'])->findOrFail($roleId);

        $company = $role->company;

        $activeCategoryApplicationIds = $this->activeCategoryApplicationIds($company->user_id);

        if ($activeCategoryApplicationIds->isEmpty()) {
            abort(403, 'Anda belum memiliki package aktif.');
        }

        session(['active_company_id' => $company->id]);

        $branchOffice = $role->branchOfficeUnit?->branchOffice;
        $unit = $role->branchOfficeUnit;

        $categoryApplications = CategoryApplication::where('status', 'active')
            ->whereIn('id', $activeCategoryApplicationIds)
            ->orderBy('name')
            ->get();

        $applicationMenus = ApplicationMenu::where('status', 'active')
            ->whereIn('category_application_id', $activeCategoryApplicationIds)
            ->orderBy('name')
            ->get();

        return view('user.profile.company-role-menus.create', compact(
            'company',
            'branchOffice',
            'unit',
            'role',
            'categoryApplications',
            'applicationMenus'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);
        $activeCategoryApplicationIds = $this->activeCategoryApplicationIds($company->user_id);

        if ($activeCategoryApplicationIds->isEmpty()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Anda belum memiliki package aktif. Beli package terlebih dahulu sebelum menambah menu aplikasi.');
        }

        $validator = Validator::make($request->all(), [
            'company_role_id' => ['required', 'uuid'],
            'category_application_id' => ['required', 'uuid', Rule::in($activeCategoryApplicationIds->all())],
            'application_menu_id' => ['required', 'uuid'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $validator->after(function ($validator) use ($request, $company) {
            $roleBelongs = CompanyRole::where('id', $request->input('company_role_id'))
                ->where('company_id', $company->id)
                ->exists();

            if (! $roleBelongs) {
                $validator->errors()->add('company_role_id', 'Role tidak valid.');
            }

            $belongs = ApplicationMenu::where('id', $request->input('application_menu_id'))
                ->where('category_application_id', $request->input('category_application_id'))
                ->exists();

            if (! $belongs) {
                $validator->errors()->add('application_menu_id', 'Menu tidak sesuai dengan Category Application yang dipilih.');
            }
        });

        if ($validator->fails()) {
            return redirect()
                ->route('profile.company-role-menus.create', $request->input('company_role_id'))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $alreadyAdded = CompanyRoleMenu::where('company_id', $company->id)
            ->where('company_role_id', $validated['company_role_id'])
            ->where('application_menu_id', $validated['application_menu_id'])
            ->exists();

        if ($alreadyAdded) {
            return redirect()
                ->route('profile.company-role-menus.create', $validated['company_role_id'])
                ->with('error', 'Menu ini sudah ditambahkan untuk role tersebut.');
        }

        CompanyRoleMenu::create([
            'company_id' => $company->id,
            'company_role_id' => $validated['company_role_id'],
            'category_application_id' => $validated['category_application_id'],
            'application_menu_id' => $validated['application_menu_id'],
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('profile.edit', ['tab' => 'applications'])
            ->with('success', 'Menu aplikasi berhasil ditambahkan untuk role tersebut.');
    }

    public function show(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $roleMenu = CompanyRoleMenu::where('company_id', $company->id)
            ->with(['companyRole.branchOfficeUnit.branchOffice', 'categoryApplication', 'applicationMenu'])
            ->findOrFail($id);

        $role = $roleMenu->companyRole;
        $unit = $role?->branchOfficeUnit;
        $branchOffice = $unit?->branchOffice;

        return view('user.profile.company-role-menus.show', compact('company', 'branchOffice', 'unit', 'role', 'roleMenu'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);
        $roleMenu = CompanyRoleMenu::where('company_id', $company->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:active,inactive'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'applications'])
                ->withErrors($validator, 'editRoleMenu' . $roleMenu->id)
                ->withInput();
        }

        $roleMenu->update($validator->validated());

        return redirect()
            ->route('profile.edit', ['tab' => 'applications'])
            ->with('success', 'Status menu aplikasi berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);
        $roleMenu = CompanyRoleMenu::where('company_id', $company->id)->findOrFail($id);
        $roleMenu->delete();

        return redirect()
            ->route('profile.edit', ['tab' => 'applications'])
            ->with('success', 'Menu aplikasi berhasil dihapus.');
    }

}
