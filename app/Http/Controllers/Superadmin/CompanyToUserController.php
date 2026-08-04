<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\BranchOfficeUnit;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\CompanyToUser;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Company Users" in the sidebar — superadmin-wide view over every
 * company_to_users row (who belongs to which company, under which
 * role), across every company. The user-facing equivalent is User\
 * Profile\CompanyUserController, which only ever touches the logged-in
 * user's own company; this one has no such scoping.
 *
 * A member can also be reassigned to a branch office/unit from here
 * (branch_office_id/branch_office_unit_id — both nullable, same
 * "optional placement" convention as the user-facing form) — the main
 * reason this exists: a CS agent fixing a member who was put under the
 * wrong branch/unit, without needing the company owner to do it.
 */
class CompanyToUserController extends Controller
{
    public function index(): View
    {
        $companyToUsers = CrudAdmin::getAll(
            modelClass: CompanyToUser::class,
            relations: ['user', 'company', 'role', 'branchOffice', 'branchOfficeUnit'],
        );

        return view('superadmin.company-to-user.index', compact('companyToUsers'));
    }

    public function create(): View
    {
        $companies = Company::orderBy('name')->get(['id', 'name', 'company_id']);
        $roles = CompanyRole::orderBy('name')->get(['id', 'name', 'company_id']);
        $branchOffices = BranchOffice::orderBy('name')->get(['id', 'name', 'company_id']);
        $branchOfficeUnits = BranchOfficeUnit::orderBy('name')->get(['id', 'name', 'branch_office_id']);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.company-to-user.create', compact('companies', 'roles', 'branchOffices', 'branchOfficeUnits', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        try {
            CrudAdmin::store(CompanyToUser::class, $validated);
        } catch (QueryException $e) {
            return back()
                ->withInput()
                ->with('error', 'User tersebut sudah menjadi anggota company ini.');
        }

        return redirect()
            ->route('company-to-user.index')
            ->with('success', 'Company user berhasil ditambahkan.');
    }

    public function edit(string $id): View
    {
        $companyToUser = CrudAdmin::find(CompanyToUser::class, $id, relations: ['user', 'company', 'role']);

        // Only roles/branch offices belonging to THIS membership's
        // company — company itself isn't editable here (removing/
        // re-adding is how you'd move someone to a different company),
        // only role/branch office/unit/status.
        $roles = CompanyRole::where('company_id', $companyToUser->company_id)
            ->orderBy('name')
            ->get();

        $branchOffices = BranchOffice::where('company_id', $companyToUser->company_id)
            ->orderBy('name')
            ->get();

        $branchOfficeUnits = BranchOfficeUnit::whereIn('branch_office_id', $branchOffices->pluck('id'))
            ->orderBy('name')
            ->get();

        return view('superadmin.company-to-user.edit', compact('companyToUser', 'roles', 'branchOffices', 'branchOfficeUnits'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $companyToUser = CrudAdmin::find(CompanyToUser::class, $id);

        $validated = $request->validate([
            'company_role_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) use ($companyToUser) {
                    $belongs = CompanyRole::where('id', $value)
                        ->where('company_id', $companyToUser->company_id)
                        ->exists();

                    if (! $belongs) {
                        $fail('Role yang dipilih tidak sesuai dengan company user ini.');
                    }
                },
            ],
            'branch_office_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($companyToUser) {
                    $belongs = BranchOffice::where('id', $value)
                        ->where('company_id', $companyToUser->company_id)
                        ->exists();

                    if (! $belongs) {
                        $fail('Branch office yang dipilih tidak sesuai dengan company user ini.');
                    }
                },
            ],
            'branch_office_unit_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($request) {
                    if (! $request->filled('branch_office_id')) {
                        $fail('Pilih branch office terlebih dahulu.');

                        return;
                    }

                    $belongs = BranchOfficeUnit::where('id', $value)
                        ->where('branch_office_id', $request->input('branch_office_id'))
                        ->exists();

                    if (! $belongs) {
                        $fail('Unit/Divisi yang dipilih tidak sesuai dengan branch office ini.');
                    }
                },
            ],
            'status' => ['required', 'in:active,inactive'],
        ]);

        CrudAdmin::update(CompanyToUser::class, $id, $validated);

        return redirect()
            ->route('company-to-user.index')
            ->with('success', 'Company user berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(CompanyToUser::class, $id);

        return redirect()
            ->route('company-to-user.index')
            ->with('success', 'Company user berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'company_role_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) use ($request) {
                    $belongs = CompanyRole::where('id', $value)
                        ->where('company_id', $request->input('company_id'))
                        ->exists();

                    if (! $belongs) {
                        $fail('Role yang dipilih tidak sesuai dengan company yang dipilih.');
                    }
                },
            ],
            'branch_office_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($request) {
                    $belongs = BranchOffice::where('id', $value)
                        ->where('company_id', $request->input('company_id'))
                        ->exists();

                    if (! $belongs) {
                        $fail('Branch office yang dipilih tidak sesuai dengan company yang dipilih.');
                    }
                },
            ],
            'branch_office_unit_id' => [
                'nullable',
                'uuid',
                function ($attribute, $value, $fail) use ($request) {
                    if (! $request->filled('branch_office_id')) {
                        $fail('Pilih branch office terlebih dahulu.');

                        return;
                    }

                    $belongs = BranchOfficeUnit::where('id', $value)
                        ->where('branch_office_id', $request->input('branch_office_id'))
                        ->exists();

                    if (! $belongs) {
                        $fail('Unit/Divisi yang dipilih tidak sesuai dengan branch office ini.');
                    }
                },
            ],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
