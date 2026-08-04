<?php

namespace App\Http\Controllers\Superadmin\HelpCenters;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\CategoryHelpCenter;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for help-center categories (e.g. "Billing",
 * "Technical"). Same shape as Superadmin\CategoryApplicationController —
 * all data access goes through CrudAdmin, which enforces the
 * superadmin-only guard and writes every store/update/delete to the
 * audit_logs table.
 */
class CategoryHelpCenterController extends Controller
{
    public function index(Request $request): View
    {
        $categoryHelpCenters = CrudAdmin::getAll(
            modelClass: CategoryHelpCenter::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.help-centers.category.index', compact('categoryHelpCenters'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.help-centers.category.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::store(CategoryHelpCenter::class, $validated);

        return redirect()
            ->route('category-help-center.index')
            ->with('success', 'Kategori help center berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $categoryHelpCenter = CrudAdmin::find(CategoryHelpCenter::class, $id, relations: ['user', 'helpCenters']);

        return view('superadmin.help-centers.category.show', compact('categoryHelpCenter'));
    }

    public function edit(string $id): View
    {
        $categoryHelpCenter = CrudAdmin::find(CategoryHelpCenter::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.help-centers.category.edit', compact('categoryHelpCenter', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(CategoryHelpCenter::class, $id, $validated);

        return redirect()
            ->route('category-help-center.index')
            ->with('success', 'Kategori help center berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(CategoryHelpCenter::class, $id);

        return redirect()
            ->route('category-help-center.index')
            ->with('success', 'Kategori help center berhasil dihapus.');
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
