<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebArticle;
use App\Models\WebCategoryArticle;
use App\Models\WebMetaTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Superadmin CRUD for articles (App\Models\WebArticle). Same shape as
 * Superadmin\Web\CategoryArticleController — data access through
 * CrudAdmin, images through App\Helpers\WebImageUploader — plus:
 * `meta_tags` is a many-to-many pick list from the App\Models\WebMetaTag
 * catalog (synced via the before/afterCreate|Update hooks CrudAdmin
 * exposes, since it isn't a plain column CrudAdmin::store/update would
 * otherwise try to mass-assign), and `count_read` is intentionally left
 * out of the form entirely — it's maintained by the future public
 * article page, not editable here.
 */
class ArticleController extends Controller
{
    private const IMAGE_SUBDIRECTORY = 'articles';
    private const META_IMAGE_SUBDIRECTORY = 'articles/meta';

    public function index(Request $request): View
    {
        $articles = CrudAdmin::getAll(
            modelClass: WebArticle::class,
            relations: ['user', 'category'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['title', 'description'],
        );

        return view('superadmin.web.articles.index', compact('articles'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $categories = WebCategoryArticle::orderBy('name')->get(['id', 'name']);
        $metaTags = WebMetaTag::orderBy('name')->get(['id', 'name']);

        return view('superadmin.web.articles.create', compact('users', 'categories', 'metaTags'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['title']);

        $metaTagIds = $validated['meta_tags'] ?? [];
        unset($validated['meta_tags']);

        $validated['images'] = WebImageUploader::upload($request->file('images'), self::IMAGE_SUBDIRECTORY);

        if ($request->hasFile('meta_images')) {
            $validated['meta_images'] = WebImageUploader::upload($request->file('meta_images'), self::META_IMAGE_SUBDIRECTORY);
        }

        CrudAdmin::store(
            WebArticle::class,
            $validated,
            afterCreate: function ($model) use ($metaTagIds) {
                $model->metaTags()->sync($metaTagIds);
            }
        );

        return redirect()
            ->route('web.articles.index')
            ->with('success', 'Artikel berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $article = CrudAdmin::find(WebArticle::class, $id, relations: ['metaTags']);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $categories = WebCategoryArticle::orderBy('name')->get(['id', 'name']);
        $metaTags = WebMetaTag::orderBy('name')->get(['id', 'name']);

        return view('superadmin.web.articles.edit', compact('article', 'users', 'categories', 'metaTags'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request, isUpdate: true);

        $metaTagIds = $validated['meta_tags'] ?? [];
        unset($validated['meta_tags']);

        if ($request->hasFile('images')) {
            $validated['images'] = WebImageUploader::upload($request->file('images'), self::IMAGE_SUBDIRECTORY);
        }

        if ($request->hasFile('meta_images')) {
            $validated['meta_images'] = WebImageUploader::upload($request->file('meta_images'), self::META_IMAGE_SUBDIRECTORY);
        }

        CrudAdmin::update(
            WebArticle::class,
            $id,
            $validated,
            beforeUpdate: function ($model, $data) {
                if ($data['title'] !== $model->title) {
                    $data['slug'] = $this->uniqueSlug($data['title'], $model->id);
                }

                if (array_key_exists('images', $data) && $model->images && $model->images !== $data['images']) {
                    WebImageUploader::delete($model->images);
                }

                if (array_key_exists('meta_images', $data) && $model->meta_images && $model->meta_images !== $data['meta_images']) {
                    WebImageUploader::delete($model->meta_images);
                }

                return $data;
            },
            afterUpdate: function ($model) use ($metaTagIds) {
                $model->metaTags()->sync($metaTagIds);
            }
        );

        return redirect()
            ->route('web.articles.index')
            ->with('success', 'Artikel berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebArticle::class, $id, function ($model) {
            WebImageUploader::delete($model->images);

            if ($model->meta_images) {
                WebImageUploader::delete($model->meta_images);
            }
        });

        return redirect()
            ->route('web.articles.index')
            ->with('success', 'Artikel berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'web_category_article_id' => ['required', 'uuid', 'exists:web_category_articles,id'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'images' => [$isUpdate ? 'nullable' : 'required', 'image', 'max:4096'],
            'meta_keywords' => ['nullable', 'string'],
            'meta_descriptions' => ['nullable', 'string'],
            'meta_images' => ['nullable', 'image', 'max:4096'],
            'meta_tags' => ['nullable', 'array'],
            'meta_tags.*' => ['uuid', 'exists:web_meta_tags,id'],
            'date_publish' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function uniqueSlug(string $title, ?string $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'artikel';
        $slug = $base;
        $i = 1;

        while (
            WebArticle::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
