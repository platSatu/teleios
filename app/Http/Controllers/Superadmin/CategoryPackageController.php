<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\CategoryPackage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for App\Models\CategoryPackage — an older, separate
 * model from CategoryApplication (kept alongside it per this project's
 * "don't delete old code" convention). Only covers CategoryPackage's
 * actual fillable columns (user_id, name, description, status); its
 * stale package() relation (pointing at a 'packages_id' column that no
 * longer exists on the current packages table) is left untouched.
 */
class CategoryPackageController extends Controller
{
    public function index(Request $request): View
    {
        $categoryPackages = CrudAdmin::getAll(
            modelClass: CategoryPackage::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.category-package.index', compact('categoryPackages'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.category-package.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::store(CategoryPackage::class, $validated);

        return redirect()
            ->route('category-package.index')
            ->with('success', 'Category package berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $categoryPackage = CrudAdmin::find(CategoryPackage::class, $id, relations: ['user']);

        return view('superadmin.category-package.show', compact('categoryPackage'));
    }

    public function edit(string $id): View
    {
        $categoryPackage = CrudAdmin::find(CategoryPackage::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.category-package.edit', compact('categoryPackage', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(CategoryPackage::class, $id, $validated);

        return redirect()
            ->route('category-package.index')
            ->with('success', 'Category package berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(CategoryPackage::class, $id);

        return redirect()
            ->route('category-package.index')
            ->with('success', 'Category package berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
