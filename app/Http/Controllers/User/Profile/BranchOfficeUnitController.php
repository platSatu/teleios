<?php

namespace App\Http\Controllers\User\Profile;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Profile\Concerns\ScopesActivePackage;
use App\Models\BranchOffice;
use App\Models\BranchOfficeUnit;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * CRUD for the "Unit/Divisi" tab on dashboard/user/profile. Scoped two
 * levels deep, always via ownedCompanyOrFail(): a unit is only
 * reachable/creatable if its branch_office_id points at a branch office
 * that belongs to the caller's own company — never a client-supplied
 * company/branch office id taken at face value. Same pattern as
 * User\Settings\BranchOfficeUnitController before this tab existed.
 *
 * Last step of the create flow: company -> branch office -> unit. The
 * view disables/hides the add form when the company has no branch
 * offices yet (see resources/views/user/profile/index.blade.php).
 *
 * Creating NEW units additionally requires the owner to currently have
 * at least one active package (see ScopesActivePackage) — same gating
 * as BranchOfficeController::store(), Setting Users/Roles/Applications.
 * Editing/deleting an already-existing unit stays allowed regardless.
 */
class BranchOfficeUnitController extends Controller
{
    use ResolvesCompanyContext;

    use ScopesActivePackage;

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        if ($this->activeCategoryApplicationIds($company->user_id)->isEmpty()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Anda belum memiliki package aktif. Beli package terlebih dahulu sebelum menambah unit/divisi.');
        }

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'unit-divisi'])
                ->withErrors($validator, 'newBranchOfficeUnit')
                ->withInput();
        }

        BranchOfficeUnit::create($validator->validated());

        return redirect()
            ->route('profile.edit', ['tab' => 'unit-divisi'])
            ->with('success', 'Unit/Divisi berhasil ditambahkan.');
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
                ->route('profile.edit', ['tab' => 'unit-divisi'])
                ->withErrors($validator, 'editBranchOfficeUnit' . $unit->id)
                ->withInput();
        }

        $unit->update($validator->validated());

        return redirect()
            ->route('profile.edit', ['tab' => 'unit-divisi'])
            ->with('success', 'Unit/Divisi berhasil diperbarui.');
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
            ->route('profile.edit', ['tab' => 'unit-divisi'])
            ->with('success', 'Unit/Divisi berhasil dihapus.');
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
