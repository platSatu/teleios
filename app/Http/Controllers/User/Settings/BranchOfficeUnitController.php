<?php

namespace App\Http\Controllers\User\Settings;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\BranchOfficeUnit;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for "Branch Office Unit" — units belonging to a branch office,
 * which itself belongs to the company owned by the logged in user.
 * Scoped two levels deep, always via ownedCompanyOrFail(): a unit is
 * only reachable if its branch_office_id points at a branch office
 * that belongs to the caller's own company — never a client-supplied
 * company/branch office id taken at face value.
 */
class BranchOfficeUnitController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $units = BranchOfficeUnit::with('branchOffice')
            ->whereHas('branchOffice', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('user.settings.branch-office-unit.index', compact('units'));
    }

    public function create(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $branchOffices = BranchOffice::where('company_id', $company->id)
            ->orderBy('name')
            ->get();

        return view('user.settings.branch-office-unit.create', compact('branchOffices'));
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('user-settings.branch-office-units.create')
                ->withErrors($validator)
                ->withInput();
        }

        BranchOfficeUnit::create($validator->validated());

        return redirect()
            ->route('user-settings.branch-office-units.index')
            ->with('success', 'Branch office unit berhasil dibuat.');
    }

    public function edit(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $unit = BranchOfficeUnit::whereHas('branchOffice', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })
            ->where('id', $id)
            ->firstOrFail();

        $branchOffices = BranchOffice::where('company_id', $company->id)
            ->orderBy('name')
            ->get();

        return view('user.settings.branch-office-unit.edit', compact('unit', 'branchOffices'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $unit = BranchOfficeUnit::whereHas('branchOffice', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('user-settings.branch-office-units.edit', $unit->id)
                ->withErrors($validator)
                ->withInput();
        }

        $unit->update($validator->validated());

        return redirect()
            ->route('user-settings.branch-office-units.index')
            ->with('success', 'Branch office unit berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $deleted = BranchOfficeUnit::whereHas('branchOffice', function ($q) use ($company) {
            $q->where('company_id', $company->id);
        })
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            abort(404);
        }

        return redirect()
            ->route('user-settings.branch-office-units.index')
            ->with('success', 'Branch office unit berhasil dihapus.');
    }

    /**
     * branch_office_id is validated against `exists`, then re-checked
     * against the caller's own company below — `exists` alone would let
     * anyone attach a unit to a branch office they don't own just by
     * guessing its uuid.
     */
    private function validator(Request $request, Company $company)
    {
        $validator = Validator::make($request->all(), [
            'branch_office_id' => ['required', 'uuid', 'exists:branch_offices,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $validator->after(function ($validator) use ($request, $company) {
            if ($validator->errors()->has('branch_office_id')) {
                return;
            }

            $owned = BranchOffice::where('company_id', $company->id)
                ->where('id', $request->branch_office_id)
                ->exists();

            if (! $owned) {
                $validator->errors()->add('branch_office_id', 'Branch office tidak valid.');
            }
        });

        return $validator;
    }

}
