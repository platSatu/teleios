<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\CategoryApplication;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for application categories. All data access goes
 * through CrudAdmin (app/Helpers/CrudAdmin.php), which enforces the
 * superadmin-only guard and writes every store/update/delete to the
 * audit_logs table — this controller itself does no authorization or
 * auditing of its own.
 */
class CategoryApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $categoryApplications = CrudAdmin::getAll(
            modelClass: CategoryApplication::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.category-application.index', compact('categoryApplications'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.category-application.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::store(CategoryApplication::class, $validated);

        return redirect()
            ->route('category-application.index')
            ->with('success', 'Kategori aplikasi berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $categoryApplication = CrudAdmin::find(CategoryApplication::class, $id, relations: ['user', 'packages']);

        return view('superadmin.category-application.show', compact('categoryApplication'));
    }

    public function edit(string $id): View
    {
        $categoryApplication = CrudAdmin::find(CategoryApplication::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.category-application.edit', compact('categoryApplication', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(CategoryApplication::class, $id, $validated);

        return redirect()
            ->route('category-application.index')
            ->with('success', 'Kategori aplikasi berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(CategoryApplication::class, $id);

        return redirect()
            ->route('category-application.index')
            ->with('success', 'Kategori aplikasi berhasil dihapus.');
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
