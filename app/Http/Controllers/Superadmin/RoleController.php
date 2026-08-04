<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin CRUD for roles. Same shape as VoucherController — all
 * data access goes through CrudAdmin (app/Helpers/CrudAdmin.php), which
 * enforces the superadmin-only guard and writes every store/update/
 * delete to the audit_logs table.
 */
class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $roles = CrudAdmin::getAll(
            modelClass: Role::class,
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.role.index', compact('roles'));
    }

    public function create(): View
    {
        return view('superadmin.role.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::store(Role::class, $validated);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $role = CrudAdmin::find(Role::class, $id);

        return view('superadmin.role.show', compact('role'));
    }

    public function edit(string $id): View
    {
        $role = CrudAdmin::find(Role::class, $id);

        return view('superadmin.role.edit', compact('role'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(Role::class, $id, $validated);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(Role::class, $id);

        return redirect()
            ->route('roles.index')
            ->with('success', 'Role berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')
                    ->where('guard_name', $request->input('guard_name', 'web'))
                    ->ignore($request->route('id')),
            ],
            'guard_name' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
