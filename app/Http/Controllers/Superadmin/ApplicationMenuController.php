<?php

namespace App\Http\Controllers\Superadmin;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\ApplicationMenu;
use App\Models\CategoryApplication;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Application Menus" in the sidebar — superadmin CRUD for
 * App\Models\ApplicationMenu. Each Category Application defines its own
 * set of menu labels (e.g. a "Chat" category and a "CRM" category can
 * each have a differently-named menu list), so every entry here is
 * required to belong to exactly one CategoryApplication.
 */
class ApplicationMenuController extends Controller
{
    public function index(Request $request): View
    {
        $applicationMenus = CrudAdmin::getAll(
            modelClass: ApplicationMenu::class,
            relations: ['user', 'categoryApplication'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.application-menu.index', compact('applicationMenus'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $categoryApplications = CategoryApplication::orderBy('name')->get(['id', 'name']);

        return view('superadmin.application-menu.create', compact('users', 'categoryApplications'));
    }

    public function store(Request $request): RedirectResponse
    {
        CrudAdmin::store(ApplicationMenu::class, $this->validated($request));

        return redirect()
            ->route('application-menu.index')
            ->with('success', 'Application menu berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $applicationMenu = CrudAdmin::find(ApplicationMenu::class, $id, relations: ['user', 'categoryApplication']);

        return view('superadmin.application-menu.show', compact('applicationMenu'));
    }

    public function edit(string $id): View
    {
        $applicationMenu = CrudAdmin::find(ApplicationMenu::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $categoryApplications = CategoryApplication::orderBy('name')->get(['id', 'name']);

        return view('superadmin.application-menu.edit', compact('applicationMenu', 'users', 'categoryApplications'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        CrudAdmin::update(ApplicationMenu::class, $id, $this->validated($request));

        return redirect()
            ->route('application-menu.index')
            ->with('success', 'Application menu berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(ApplicationMenu::class, $id);

        return redirect()
            ->route('application-menu.index')
            ->with('success', 'Application menu berhasil dihapus.');
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
            'status' => ['required', 'in:active,inactive'],
        ]);
    }
}
