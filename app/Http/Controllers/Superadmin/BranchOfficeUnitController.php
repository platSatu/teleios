<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\BranchOfficeUnit;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Branch Office Units" in the sidebar — superadmin-wide CRUD over
 * every unit/divisi, across every branch office/company (unlike
 * User\Profile\BranchOfficeUnitController, which only ever touches the
 * units of the logged-in user's own company). Same "problem solver"
 * role as BranchOfficeController/CompanyRoleController.
 *
 * Create/Edit show a Company select purely as a client-side filter for
 * the Branch Office select (a unit's actual FK is branch_office_id
 * only — company is derived through it) — same two-level select
 * pattern as Company -> Role on superadmin.company-to-user's create
 * page.
 */
class BranchOfficeUnitController extends Controller
{
    public function index(Request $request): View
    {
        $branchOfficeUnits = CrudAdmin::getAll(
            modelClass: BranchOfficeUnit::class,
            relations: ['branchOffice', 'branchOffice.company'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.branch-office-unit.index', compact('branchOfficeUnits'));
    }

    public function create(): View
    {
        $companies = Company::orderBy('name')->get(['id', 'name', 'company_id']);
        $branchOffices = BranchOffice::orderBy('name')->get(['id', 'name', 'company_id']);

        return view('superadmin.branch-office-unit.create', compact('companies', 'branchOffices'));
    }

    public function store(Request $request): RedirectResponse
    {
        CrudAdmin::store(BranchOfficeUnit::class, $this->validated($request));

        return redirect()
            ->route('branch-office-unit.index')
            ->with('success', 'Unit/Divisi berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $branchOfficeUnit = CrudAdmin::find(BranchOfficeUnit::class, $id, relations: ['branchOffice', 'branchOffice.company']);
        $companies = Company::orderBy('name')->get(['id', 'name', 'company_id']);
        $branchOffices = BranchOffice::orderBy('name')->get(['id', 'name', 'company_id']);

        return view('superadmin.branch-office-unit.edit', compact('branchOfficeUnit', 'companies', 'branchOffices'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        CrudAdmin::update(BranchOfficeUnit::class, $id, $this->validated($request));

        return redirect()
            ->route('branch-office-unit.index')
            ->with('success', 'Unit/Divisi berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(BranchOfficeUnit::class, $id);

        return redirect()
            ->route('branch-office-unit.index')
            ->with('success', 'Unit/Divisi berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'branch_office_id' => ['required', 'uuid', 'exists:branch_offices,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
