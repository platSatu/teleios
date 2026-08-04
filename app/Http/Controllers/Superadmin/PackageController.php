<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\CategoryApplication;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Superadmin CRUD for packages. All data access goes through CrudAdmin
 * (app/Helpers/CrudAdmin.php), which enforces the superadmin-only guard
 * and writes every store/update/delete to the audit_logs table.
 */
class PackageController extends Controller
{
    public function index(Request $request): View
    {
        $packages = CrudAdmin::getAll(
            modelClass: Package::class,
            relations: ['user', 'categoryApplication'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.package.index', compact('packages'));
    }

    public function create(): View
    {
        [$users, $categoryApplications] = $this->formOptions();

        return view('superadmin.package.create', compact('users', 'categoryApplications'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::store(Package::class, $validated);

        return redirect()
            ->route('package.index')
            ->with('success', 'Package berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $package = CrudAdmin::find(Package::class, $id, relations: ['user', 'categoryApplication']);

        return view('superadmin.package.show', compact('package'));
    }

    public function edit(string $id): View
    {
        $package = CrudAdmin::find(Package::class, $id);
        [$users, $categoryApplications] = $this->formOptions();

        return view('superadmin.package.edit', compact('package', 'users', 'categoryApplications'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(Package::class, $id, $validated);

        return redirect()
            ->route('package.index')
            ->with('success', 'Package berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(Package::class, $id);

        return redirect()
            ->route('package.index')
            ->with('success', 'Package berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'category_application_id' => ['required', 'uuid', 'exists:category_applications,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function formOptions(): array
    {
        return [
            User::orderBy('name')->get(['id', 'name', 'email']),
            CategoryApplication::orderBy('name')->get(['id', 'name']),
        ];
    }
}
