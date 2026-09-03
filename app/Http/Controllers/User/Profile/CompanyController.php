<?php

namespace App\Http\Controllers\User\Profile;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\CompanyToUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * CRUD for the "Company" tab on dashboard/user/profile — now a list of
 * every company the logged-in user owns (a user can own more than one),
 * not a single lazily-created row. Replaces the old single-company
 * create-or-update toggle that used to live in User\Profile\
 * ProfileController::updateCompany() (see that method's git history) —
 * that assumed exactly one Company per user, which no longer holds.
 *
 * store() always creates a NEW company (never "update the one you
 * already have" — that ambiguity is gone now that there can be several).
 * Creating a company still seeds a default "Owner" CompanyRole and links
 * the creator to it via CompanyToUser, same as before, in one
 * transaction.
 *
 * Every method re-verifies `Company::user_id === Auth::id()` on the
 * specific {id} in the route — never a session/context guess — so one
 * owned company can't be edited/shown/deleted by tampering with the id
 * of another company you happen to also own (not a real risk, but keeps
 * the ownership check uniform) or, more importantly, a company you don't
 * own at all.
 */
class CompanyController extends Controller
{
    // No index() here — the Company tab's LIST stays embedded in the
    // shared dashboard/user/profile page (User\Profile\ProfileController::
    // index() loads $companies, resources/views/user/profile/index.blade.php
    // renders the tab-pane), same pattern as every other tab's index. Only
    // Create/Edit/Show — the non-trivial forms — get their own dedicated
    // pages here, matching CompanyUserController's precedent.

    public function create(): View
    {
        return view('user.profile.companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.companies.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['user_id'] = Auth::id();
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        DB::transaction(function () use ($validated) {
            $company = Company::create($validated);

            $ownerRole = CompanyRole::create([
                'company_id' => $company->id,
                'name' => 'Owner',
                'description' => 'Pemilik company dengan akses penuh.',
                'status' => 'active',
                // Explicit rather than relying on the column's DB
                // default -- a solo owner who also teaches is the
                // common case, so the auto-created Owner role starts
                // out counted as a Pengajar too. The owner can uncheck
                // this later from the Roles tab once real teaching
                // staff are added. See the is_pengajar migration's
                // docblock for the full reasoning.
                'is_pengajar' => true,
            ]);

            CompanyToUser::create([
                'user_id' => Auth::id(),
                'company_id' => $company->id,
                'company_role_id' => $ownerRole->id,
                'status' => 'active',
            ]);
        });

        return redirect()
            ->route('profile.edit', ['tab' => 'company'])
            ->with('success', 'Company berhasil ditambahkan.');
    }

    public function show(string $id): View
    {
        $company = $this->ownedCompanyOrFail($id);

        return view('user.profile.companies.show', compact('company'));
    }

    public function edit(string $id): View
    {
        $company = $this->ownedCompanyOrFail($id);

        return view('user.profile.companies.edit', compact('company'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($id);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('profile.companies.edit', $company->id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();
        $validated['slug'] = $this->uniqueSlug($validated['name'], $company->id);

        if ($request->hasFile('logo')) {
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }

            $validated['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company->update($validated);

        return redirect()
            ->route('profile.edit', ['tab' => 'company'])
            ->with('success', 'Company berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $company = $this->ownedCompanyOrFail($id);

        if ($company->branchOffices()->exists()) {
            return redirect()
                ->route('profile.edit', ['tab' => 'company'])
                ->with('error', 'Company masih punya branch. Hapus semua branch-nya dulu.');
        }

        if ($company->logo) {
            Storage::disk('public')->delete($company->logo);
        }

        $company->delete();

        // If the deleted company was the one currently "active" in the
        // session (see BranchOfficeController and friends), clear it so
        // the other tabs don't keep pointing at a company that no longer
        // exists.
        if (session('active_company_id') === $id) {
            session()->forget('active_company_id');
        }

        return redirect()
            ->route('profile.edit', ['tab' => 'company'])
            ->with('success', 'Company berhasil dihapus.');
    }

    private function ownedCompanyOrFail(string $id): Company
    {
        return Company::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
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
     * Same approach as User\Profile\BranchOfficeController::uniqueSlug().
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
