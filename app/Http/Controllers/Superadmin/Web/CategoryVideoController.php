<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebCategoryVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Superadmin CRUD for video categories (App\Models\WebCategoryVideo).
 * Same shape as Superadmin\Web\CategoryArticleController — data access
 * through CrudAdmin, `thumbnail` upload through App\Helpers\WebImageUploader,
 * cropped to a fixed 16:9 box (uploadCover) rather than just scaled down,
 * since this is shown in a uniform thumbnail grid.
 */
class CategoryVideoController extends Controller
{
    private const IMAGE_SUBDIRECTORY = 'category-videos';
    private const THUMB_WIDTH = 640;
    private const THUMB_HEIGHT = 360;

    public function index(Request $request): View
    {
        $categoryVideos = CrudAdmin::getAll(
            modelClass: WebCategoryVideo::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['name', 'description'],
        );

        return view('superadmin.web.category-videos.index', compact('categoryVideos'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.category-videos.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $validated['slug'] = $this->uniqueSlug($validated['name']);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = WebImageUploader::uploadCover(
                $request->file('thumbnail'),
                self::IMAGE_SUBDIRECTORY,
                self::THUMB_WIDTH,
                self::THUMB_HEIGHT
            );
        }

        CrudAdmin::store(WebCategoryVideo::class, $validated);

        return redirect()
            ->route('web.category-videos.index')
            ->with('success', 'Kategori video berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $categoryVideo = CrudAdmin::find(WebCategoryVideo::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.category-videos.edit', compact('categoryVideo', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $this->validated($request);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = WebImageUploader::uploadCover(
                $request->file('thumbnail'),
                self::IMAGE_SUBDIRECTORY,
                self::THUMB_WIDTH,
                self::THUMB_HEIGHT
            );
        }

        CrudAdmin::update(WebCategoryVideo::class, $id, $validated, function ($model, $data) {
            if ($data['name'] !== $model->name) {
                $data['slug'] = $this->uniqueSlug($data['name'], $model->id);
            }

            if (array_key_exists('thumbnail', $data) && $model->thumbnail && $model->thumbnail !== $data['thumbnail']) {
                WebImageUploader::delete($model->thumbnail);
            }

            return $data;
        });

        return redirect()
            ->route('web.category-videos.index')
            ->with('success', 'Kategori video berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebCategoryVideo::class, $id, function ($model) {
            WebImageUploader::delete($model->thumbnail);
        });

        return redirect()
            ->route('web.category-videos.index')
            ->with('success', 'Kategori video berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'description' => ['nullable', 'string'],
            'date_publish' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    private function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kategori-video';
        $slug = $base;
        $i = 1;

        while (
            WebCategoryVideo::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
