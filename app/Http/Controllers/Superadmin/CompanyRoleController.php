<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRole;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Company Roles" in the sidebar — superadmin-wide view over every
 * CompanyRole, across every company (unlike User\Profile\
 * CompanyRoleController, which only ever touches the roles of the
 * logged-in user's own company). No "Owner" special-casing on destroy()
 * here like the user-facing controller has — a superadmin is trusted to
 * know what they're doing; the DB's restrictOnDelete FK (company_to_users.
 * company_role_id) is the actual backstop, caught below with a friendly
 * message instead of a raw SQL error.
 */
class CompanyRoleController extends Controller
{
    public function index(Request $request): View
    {
        $companyRoles = CrudAdmin::getAll(
            modelClass: CompanyRole::class,
            relations: ['company'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.company-role.index', compact('companyRoles'));
    }

    public function create(): View
    {
        $companies = Company::orderBy('name')->get(['id', 'name', 'company_id']);

        return view('superadmin.company-role.create', compact('companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        CrudAdmin::store(CompanyRole::class, $this->validated($request));

        return redirect()
            ->route('company-role.index')
            ->with('success', 'Company role berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $companyRole = CrudAdmin::find(CompanyRole::class, $id, relations: ['company']);
        $companies = Company::orderBy('name')->get(['id', 'name', 'company_id']);

        return view('superadmin.company-role.edit', compact('companyRole', 'companies'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        CrudAdmin::update(CompanyRole::class, $id, $this->validated($request));

        return redirect()
            ->route('company-role.index')
            ->with('success', 'Company role berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        try {
            CrudAdmin::delete(CompanyRole::class, $id);
        } catch (QueryException $e) {
            return back()->with(
                'error',
                'Role ini masih dipakai oleh user (lihat menu Company Users) — pindahkan usernya ke role lain dulu sebelum menghapus.'
            );
        }

        return redirect()
            ->route('company-role.index')
            ->with('success', 'Company role berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'uuid', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
