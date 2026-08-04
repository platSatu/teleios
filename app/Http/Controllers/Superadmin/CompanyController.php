<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\CompanyRoleMenu;
use App\Models\CompanyToUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Data Company" in the sidebar — superadmin-wide CRUD over every
 * company in the app (not scoped to "the logged in user's own company"
 * like User\Profile\ProfileController's Company tab is). Lets a
 * superadmin create/fix/delete a company on a user's behalf — the
 * "problem solver" role this was built for.
 */
class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $companies = CrudAdmin::getAll(
            modelClass: Company::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'company_id', 'slug', 'email'],
        );

        return view('superadmin.company.index', compact('companies'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.company.create', compact('users'));
    }

    /**
     * Also seeds a default "Owner" CompanyRole and links the chosen
     * user to it via CompanyToUser — the same invariant User\Profile\
     * ProfileController::updateCompany() sets up when a user creates
     * their own company. Without this, a superadmin-created company
     * would have no roles/members at all, which the Setting Users /
     * Roles tabs (and this controller's own destroy() cleanup) assume
     * always exist together.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        DB::transaction(function () use ($validated) {
            $company = CrudAdmin::store(Company::class, $validated);

            $ownerRole = CompanyRole::create([
                'company_id' => $company->id,
                'name' => 'Owner',
                'description' => 'Pemilik company dengan akses penuh.',
                'status' => 'active',
            ]);

            CompanyToUser::create([
                'user_id' => $company->user_id,
                'company_id' => $company->id,
                'company_role_id' => $ownerRole->id,
                'status' => 'active',
            ]);
        });

        return redirect()
            ->route('company.index')
            ->with('success', 'Company berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $company = CrudAdmin::find(Company::class, $id, relations: ['user']);

        $roles = CompanyRole::where('company_id', $company->id)
            ->orderBy('created_at')
            ->get();

        $members = CompanyToUser::where('company_id', $company->id)
            ->with(['user', 'role'])
            ->orderBy('created_at')
            ->get();

        // So a superadmin can see everything about this company — roles,
        // members, AND which menus it has switched on — from one page
        // when troubleshooting a complaint, instead of also having to
        // separately search the Company Role Menus list by company name.
        $roleMenus = CompanyRoleMenu::where('company_id', $company->id)
            ->with(['categoryApplication', 'applicationMenu'])
            ->orderBy('created_at')
            ->get();

        // Same "everything about this company on one page" rationale as
        // roles/members/roleMenus above — units are eager-loaded onto
        // each branch office so the Unit/Divisi section can list them
        // grouped by branch office without another query per row.
        $branchOffices = BranchOffice::where('company_id', $company->id)
            ->with('units')
            ->orderBy('created_at')
            ->get();

        return view('superadmin.company.show', compact('company', 'roles', 'members', 'roleMenus', 'branchOffices'));
    }

    public function edit(string $id): View
    {
        $company = CrudAdmin::find(Company::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.company.edit', compact('company', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = CrudAdmin::find(Company::class, $id);

        $validated = $this->validated($request, $id);
        $validated['slug'] = $this->uniqueSlug($validated['name'], $id);

        if ($request->hasFile('logo')) {
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }

            $validated['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        CrudAdmin::update(Company::class, $id, $validated);

        return redirect()
            ->route('company.index')
            ->with('success', 'Company berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(Company::class, $id, beforeDelete: function ($company) {
            // Clean up children explicitly rather than leaning on the DB
            // cascade: company_to_users.company_role_id is
            // restrictOnDelete against company_roles, and relying on
            // MySQL to resolve two independent company_id cascades
            // (into company_roles and into company_to_users) in an
            // order that never trips that restrict isn't guaranteed.
            // Deleting members first, then roles, sidesteps the
            // ambiguity entirely.
            CompanyToUser::where('company_id', $company->id)->delete();
            CompanyRole::where('company_id', $company->id)->delete();

            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
        });

        return redirect()
            ->route('company.index')
            ->with('success', 'Company berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    /**
     * Str::slug($name), re-rolled with a numeric suffix until it's
     * unique — same approach as User\Profile\ProfileController's
     * private uniqueSlug(), duplicated here rather than shared since
     * the two controllers have no natural common parent.
     */
    private function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $suffix = 2;

        while (
            Company::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
