<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\ApplicationMenu;
use App\Models\CategoryApplication;
use App\Models\Company;
use App\Models\CompanyRoleMenu;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Company Role Menus" in the sidebar — superadmin-wide, cross-company
 * view over App\Models\CompanyRoleMenu (which Application Menu entries
 * each company has switched on). The per-company, self-service version
 * of this same CRUD is User\Profile\CompanyRoleMenuController, reached
 * from the "Applications" tab on dashboard/user/profile; this one has
 * no ownership scoping, since it exists specifically so a superadmin
 * can fix a company's menu setup when they report something's missing
 * or wrong.
 */
class CompanyRoleMenuController extends Controller
{
    public function index(): View
    {
        $companyRoleMenus = CrudAdmin::getAll(
            modelClass: CompanyRoleMenu::class,
            relations: ['company', 'categoryApplication', 'applicationMenu'],
        );

        return view('superadmin.company-role-menu.index', compact('companyRoleMenus'));
    }

    public function create(): View
    {
        $companies = Company::orderBy('name')->get(['id', 'name', 'company_id']);
        $categoryApplications = CategoryApplication::orderBy('name')->get(['id', 'name']);
        $applicationMenus = ApplicationMenu::orderBy('name')->get(['id', 'name', 'category_application_id']);

        return view('superadmin.company-role-menu.create', compact('companies', 'categoryApplications', 'applicationMenus'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            CrudAdmin::store(CompanyRoleMenu::class, $validated);
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->with('error', 'Company ini sudah punya menu tersebut.');
        }

        return redirect()
            ->route('company-role-menu.index')
            ->with('success', 'Company role menu berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $companyRoleMenu = CrudAdmin::find(CompanyRoleMenu::class, $id, relations: ['company']);
        $categoryApplications = CategoryApplication::orderBy('name')->get(['id', 'name']);
        $applicationMenus = ApplicationMenu::orderBy('name')->get(['id', 'name', 'category_application_id']);

        return view('superadmin.company-role-menu.edit', compact('companyRoleMenu', 'categoryApplications', 'applicationMenus'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            CrudAdmin::update(CompanyRoleMenu::class, $id, $validated);
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->with('error', 'Company ini sudah punya menu tersebut.');
        }

        return redirect()
            ->route('company-role-menu.index')
            ->with('success', 'Company role menu berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(CompanyRoleMenu::class, $id);

        return redirect()
            ->route('company-role-menu.index')
            ->with('success', 'Company role menu berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'category_application_id' => ['required', 'uuid', 'exists:category_applications,id'],
            'application_menu_id' => ['required', 'uuid', 'exists:application_menus,id'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        // Consistency check: the chosen menu actually belongs to the
        // chosen category — the JS in create/edit filters the <select>
        // down to matching options, but that's client-side only.
        $belongs = ApplicationMenu::where('id', $validated['application_menu_id'])
            ->where('category_application_id', $validated['category_application_id'])
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'application_menu_id' => 'Menu tidak sesuai dengan Category Application yang dipilih.',
            ]);
        }

        return $validated;
    }
}
