<?php

namespace App\Http\Controllers\User\Settings;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * CRUD for "Branch Office" — branches belonging to the company owned by
 * the logged in user. Scoped the same way as Chat\MessageScheduleController
 * and friends: always via ownedCompanyOrFail(), never a client-supplied
 * company id.
 */
class BranchOfficeController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $branchOffices = BranchOffice::where('company_id', $company->id)
            ->when($request->search, function ($q, $search) {
                $q->where('name', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('user.settings.branch-office.index', compact('branchOffices'));
    }

    public function create(): View
    {
        return view('user.settings.branch-office.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('user-settings.branch-offices.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['company_id'] = $company->id;
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        BranchOffice::create($validated);

        return redirect()
            ->route('user-settings.branch-offices.index')
            ->with('success', 'Branch office berhasil dibuat.');
    }

    public function edit(Request $request, string $id): View
    {
        $company = $this->ownedCompanyOrFail($request);

        $branchOffice = BranchOffice::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        return view('user.settings.branch-office.edit', compact('branchOffice'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $branchOffice = BranchOffice::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('user-settings.branch-offices.edit', $branchOffice->id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['slug'] = $this->uniqueSlug($validated['name'], $branchOffice->id);

        $branchOffice->update($validated);

        return redirect()
            ->route('user-settings.branch-offices.index')
            ->with('success', 'Branch office berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($request);

        $deleted = BranchOffice::where('company_id', $company->id)
            ->where('id', $id)
            ->delete();

        if (! $deleted) {
            abort(404);
        }

        return redirect()
            ->route('user-settings.branch-offices.index')
            ->with('success', 'Branch office berhasil dihapus.');
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    /**
     * Str::slug($name), re-rolled with a numeric suffix (-2, -3, ...)
     * until it doesn't collide with another branch office's slug.
     * $ignoreId excludes the record currently being updated so saving
     * without changing the name doesn't trip over its own slug.
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
