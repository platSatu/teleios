<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Branch Offices" in the sidebar — superadmin-wide CRUD over every
 * branch office, across every company (unlike User\Profile\
 * BranchOfficeController, which only ever touches the branch offices of
 * the logged-in user's own company). Lets a superadmin/CS agent
 * create/fix/delete a branch office on a company's behalf when they
 * report a problem — same "problem solver" role as Superadmin\
 * CompanyController.
 *
 * Deleting a branch office cascades to its units at the DB level
 * (branch_office_units.branch_office_id is cascadeOnDelete, unlike the
 * user-facing controller which blocks the delete while units still
 * exist) — a superadmin is trusted to know what they're doing, same
 * stance as CompanyRoleController's docblock; the confirm dialog in the
 * view spells out the consequence before it happens, and CrudAdmin
 * still writes an audit log entry either way.
 */
class BranchOfficeController extends Controller
{
    public function index(Request $request): View
    {
        $branchOffices = CrudAdmin::getAll(
            modelClass: BranchOffice::class,
            relations: ['company'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'slug', 'address'],
        );

        return view('superadmin.branch-office.index', compact('branchOffices'));
    }

    public function create(): View
    {
        $companies = Company::orderBy('name')->get(['id', 'name', 'company_id']);

        return view('superadmin.branch-office.create', compact('companies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        CrudAdmin::store(BranchOffice::class, $validated);

        return redirect()
            ->route('branch-office.index')
            ->with('success', 'Branch office berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $branchOffice = CrudAdmin::find(BranchOffice::class, $id, relations: ['company']);
        $companies = Company::orderBy('name')->get(['id', 'name', 'company_id']);

        return view('superadmin.branch-office.edit', compact('branchOffice', 'companies'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['name'], $id);

        CrudAdmin::update(BranchOffice::class, $id, $validated);

        return redirect()
            ->route('branch-office.index')
            ->with('success', 'Branch office berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(BranchOffice::class, $id);

        return redirect()
            ->route('branch-office.index')
            ->with('success', 'Branch office berhasil dihapus.');
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
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    /**
     * Str::slug($name), re-rolled with a numeric suffix until it's
     * unique — same approach as Superadmin\CompanyController's private
     * uniqueSlug(), duplicated here rather than shared since the two
     * controllers have no natural common parent.
     */
    private function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'branch-office';
        $slug = $base;
        $suffix = 2;

        while (
            BranchOffice::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
