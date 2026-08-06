<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebMetaTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Superadmin CRUD for reusable SEO meta tags (App\Models\WebMetaTag),
 * attachable to public web content later (articles, videos, ...). Same
 * shape as Superadmin\CategoryApplicationController / Superadmin\
 * Documentation\CategoryDocumentationController — all data access goes
 * through CrudAdmin (app/Helpers/CrudAdmin.php), which enforces the
 * superadmin-only guard and writes every store/update/delete to the
 * audit_logs table.
 */
class MetaTagController extends Controller
{
    public function index(Request $request): View
    {
        $metaTags = CrudAdmin::getAll(
            modelClass: WebMetaTag::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name'],
        );

        return view('superadmin.web.meta-tags.index', compact('metaTags'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.meta-tags.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        CrudAdmin::store(WebMetaTag::class, $validated);

        return redirect()
            ->route('web.meta-tags.index')
            ->with('success', 'Meta tag berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $metaTag = CrudAdmin::find(WebMetaTag::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.meta-tags.edit', compact('metaTag', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        CrudAdmin::update(WebMetaTag::class, $id, $validated, function ($model, $data) {
            // Re-derive the slug only if the name actually changed —
            // keeps the URL stable otherwise, same approach as
            // CategoryDocumentationController::update().
            if ($data['name'] !== $model->name) {
                $data['slug'] = $this->uniqueSlug($data['name'], $model->id);
            }

            return $data;
        });

        return redirect()
            ->route('web.meta-tags.index')
            ->with('success', 'Meta tag berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebMetaTag::class, $id);

        return redirect()
            ->route('web.meta-tags.index')
            ->with('success', 'Meta tag berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'meta-tag';
        $slug = $base;
        $i = 1;

        while (
            WebMetaTag::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
