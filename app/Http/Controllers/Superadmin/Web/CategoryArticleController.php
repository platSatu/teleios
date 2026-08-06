<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebCategoryArticle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Superadmin CRUD for article categories (App\Models\WebCategoryArticle).
 * Same shape as Superadmin\Documentation\CategoryDocumentationController —
 * all data access goes through CrudAdmin — plus an `images` upload
 * handled through App\Helpers\WebImageUploader (resize + store under
 * public/web/images/category-articles).
 */
class CategoryArticleController extends Controller
{
    private const IMAGE_SUBDIRECTORY = 'category-articles';

    public function index(Request $request): View
    {
        $categoryArticles = CrudAdmin::getAll(
            modelClass: WebCategoryArticle::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.web.category-articles.index', compact('categoryArticles'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.category-articles.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        if ($request->hasFile('images')) {
            $validated['images'] = WebImageUploader::upload($request->file('images'), self::IMAGE_SUBDIRECTORY);
        }

        CrudAdmin::store(WebCategoryArticle::class, $validated);

        return redirect()
            ->route('web.category-articles.index')
            ->with('success', 'Kategori artikel berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $categoryArticle = CrudAdmin::find(WebCategoryArticle::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.category-articles.edit', compact('categoryArticle', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($request->hasFile('images')) {
            $validated['images'] = WebImageUploader::upload($request->file('images'), self::IMAGE_SUBDIRECTORY);
        }

        CrudAdmin::update(WebCategoryArticle::class, $id, $validated, function ($model, $data) {
            // Re-derive the slug only if the name actually changed.
            if ($data['name'] !== $model->name) {
                $data['slug'] = $this->uniqueSlug($data['name'], $model->id);
            }

            // A new image was uploaded above — remove the old file so
            // replaced images don't pile up on disk. If no new file was
            // uploaded, $data has no 'images' key and the existing value
            // (and file) is left untouched.
            if (array_key_exists('images', $data) && $model->images && $model->images !== $data['images']) {
                WebImageUploader::delete($model->images);
            }

            return $data;
        });

        return redirect()
            ->route('web.category-articles.index')
            ->with('success', 'Kategori artikel berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebCategoryArticle::class, $id, function ($model) {
            WebImageUploader::delete($model->images);
        });

        return redirect()
            ->route('web.category-articles.index')
            ->with('success', 'Kategori artikel berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'images' => ['nullable', 'image', 'max:4096'],
            'description' => ['nullable', 'string'],
            'date_publish' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kategori-artikel';
        $slug = $base;
        $i = 1;

        while (
            WebCategoryArticle::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
