<?php

namespace App\Http\Controllers\Superadmin\Documentation;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\CategoryDocumentation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin CRUD for public API documentation categories (e.g.
 * "Autentikasi", "Kirim Pesan") — grouping for App\Models\ApiDocumentation
 * articles, rendered publicly at GET /dokumentasi (no login required, see
 * PublicDocumentationController). Same shape as Superadmin\HelpCenters\
 * CategoryHelpCenterController — all data access goes through CrudAdmin.
 */
class CategoryDocumentationController extends Controller
{
    public function index(Request $request): View
    {
        $categories = CrudAdmin::getAll(
            modelClass: CategoryDocumentation::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.wa-api-dokumentasi.categories.index', compact('categories'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.wa-api-dokumentasi.categories.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        CrudAdmin::store(CategoryDocumentation::class, $validated);

        return redirect()
            ->route('wa-api-dokumentasi.categories.index')
            ->with('success', 'Kategori dokumentasi berhasil dibuat.');
    }

    public function show(string $id): View
    {
        $category = CrudAdmin::find(CategoryDocumentation::class, $id, relations: ['user', 'apiDocumentations']);

        return view('superadmin.wa-api-dokumentasi.categories.show', compact('category'));
    }

    public function edit(string $id): View
    {
        $category = CrudAdmin::find(CategoryDocumentation::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.wa-api-dokumentasi.categories.edit', compact('category', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request, $id);

        CrudAdmin::update(CategoryDocumentation::class, $id, $validated, function ($model, $data) {
            // Re-derive the slug only if the name actually changed —
            // same "keep the URL stable unless the name moves" approach
            // as BranchOffice/Company's uniqueSlug() usage elsewhere.
            if ($data['name'] !== $model->name) {
                $data['slug'] = $this->uniqueSlug($data['name'], $model->id);
            }

            return $data;
        });

        return redirect()
            ->route('wa-api-dokumentasi.categories.index')
            ->with('success', 'Kategori dokumentasi berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(CategoryDocumentation::class, $id);

        return redirect()
            ->route('wa-api-dokumentasi.categories.index')
            ->with('success', 'Kategori dokumentasi berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?string $ignoreId = null): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $i = 1;

        while (
            CategoryDocumentation::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
