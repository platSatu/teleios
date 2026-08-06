<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebFileUploader;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebCategoryVideo;
use App\Models\WebMetaTag;
use App\Models\WebVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Superadmin CRUD for videos (App\Models\WebVideo). Same shape as
 * Superadmin\Web\ArticleController (CrudAdmin, meta_tags many-to-many
 * sync, meta_images/meta_descriptions fallback) plus two extra
 * concerns: `videos` is a raw file upload through App\Helpers\WebFileUploader
 * (not resized, unlike `thumbnail`), and a video entry must have at
 * least one of `videos`/`link_youtube` — enforced here via
 * assertHasVideoSource() since it isn't a single-column DB constraint.
 */
class VideoController extends Controller
{
    private const THUMB_SUBDIRECTORY = 'videos';
    private const META_IMAGE_SUBDIRECTORY = 'videos/meta';
    private const VIDEO_FILE_SUBDIRECTORY = 'videos';
    private const THUMB_WIDTH = 640;
    private const THUMB_HEIGHT = 360;

    public function index(Request $request): View
    {
        $videos = CrudAdmin::getAll(
            modelClass: WebVideo::class,
            relations: ['user', 'category'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['title', 'description'],
        );

        return view('superadmin.web.videos.index', compact('videos'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $categories = WebCategoryVideo::orderBy('name')->get(['id', 'name']);
        $metaTags = WebMetaTag::orderBy('name')->get(['id', 'name']);

        return view('superadmin.web.videos.create', compact('users', 'categories', 'metaTags'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->assertHasVideoSource($request, hasExisting: false);

        $validated['slug'] = $this->uniqueSlug($validated['title']);

        $metaTagIds = $validated['meta_tags'] ?? [];
        unset($validated['meta_tags']);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = WebImageUploader::uploadCover(
                $request->file('thumbnail'),
                self::THUMB_SUBDIRECTORY,
                self::THUMB_WIDTH,
                self::THUMB_HEIGHT
            );
        }

        if ($request->hasFile('meta_images')) {
            $validated['meta_images'] = WebImageUploader::upload($request->file('meta_images'), self::META_IMAGE_SUBDIRECTORY);
        }

        if ($request->hasFile('videos')) {
            $validated['videos'] = WebFileUploader::upload($request->file('videos'), self::VIDEO_FILE_SUBDIRECTORY);
        }

        CrudAdmin::store(
            WebVideo::class,
            $validated,
            afterCreate: function ($model) use ($metaTagIds) {
                $model->metaTags()->sync($metaTagIds);
            }
        );

        return redirect()
            ->route('web.videos.index')
            ->with('success', 'Video berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $video = CrudAdmin::find(WebVideo::class, $id, relations: ['metaTags']);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $categories = WebCategoryVideo::orderBy('name')->get(['id', 'name']);
        $metaTags = WebMetaTag::orderBy('name')->get(['id', 'name']);

        return view('superadmin.web.videos.edit', compact('video', 'users', 'categories', 'metaTags'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $existing = CrudAdmin::find(WebVideo::class, $id);

        $validated = $this->validated($request);
        $this->assertHasVideoSource($request, hasExisting: (bool) $existing->videos, existingId: $id);

        $metaTagIds = $validated['meta_tags'] ?? [];
        unset($validated['meta_tags']);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = WebImageUploader::uploadCover(
                $request->file('thumbnail'),
                self::THUMB_SUBDIRECTORY,
                self::THUMB_WIDTH,
                self::THUMB_HEIGHT
            );
        }

        if ($request->hasFile('meta_images')) {
            $validated['meta_images'] = WebImageUploader::upload($request->file('meta_images'), self::META_IMAGE_SUBDIRECTORY);
        }

        if ($request->hasFile('videos')) {
            $validated['videos'] = WebFileUploader::upload($request->file('videos'), self::VIDEO_FILE_SUBDIRECTORY);
        }

        CrudAdmin::update(
            WebVideo::class,
            $id,
            $validated,
            beforeUpdate: function ($model, $data) {
                if ($data['title'] !== $model->title) {
                    $data['slug'] = $this->uniqueSlug($data['title'], $model->id);
                }

                if (array_key_exists('thumbnail', $data) && $model->thumbnail && $model->thumbnail !== $data['thumbnail']) {
                    WebImageUploader::delete($model->thumbnail);
                }

                if (array_key_exists('meta_images', $data) && $model->meta_images && $model->meta_images !== $data['meta_images']) {
                    WebImageUploader::delete($model->meta_images);
                }

                if (array_key_exists('videos', $data) && $model->videos && $model->videos !== $data['videos']) {
                    WebFileUploader::delete($model->videos);
                }

                return $data;
            },
            afterUpdate: function ($model) use ($metaTagIds) {
                $model->metaTags()->sync($metaTagIds);
            }
        );

        return redirect()
            ->route('web.videos.index')
            ->with('success', 'Video berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebVideo::class, $id, function ($model) {
            WebImageUploader::delete($model->thumbnail);

            if ($model->meta_images) {
                WebImageUploader::delete($model->meta_images);
            }

            WebFileUploader::delete($model->videos);
        });

        return redirect()
            ->route('web.videos.index')
            ->with('success', 'Video berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'web_category_video_id' => ['required', 'uuid', 'exists:web_category_videos,id'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
            'description' => ['nullable', 'string'],
            'videos' => ['nullable', 'file', 'mimes:mp4,mov,avi,wmv,mkv,webm', 'max:102400'],
            'link_youtube' => ['nullable', 'url', 'max:255'],
            'meta_keywords' => ['nullable', 'string'],
            'meta_descriptions' => ['nullable', 'string'],
            'meta_images' => ['nullable', 'image', 'max:4096'],
            'meta_tags' => ['nullable', 'array'],
            'meta_tags.*' => ['uuid', 'exists:web_meta_tags,id'],
            'date_publish' => ['nullable', 'date'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    /**
     * A video entry needs at least one of an uploaded file or a YouTube
     * link — otherwise there's nothing to actually play. Checked after
     * the main validate() call (rather than a single rule) because
     * "at least one of these two independently-nullable fields" needs
     * to see both at once, and on update also needs to know whether a
     * file already on record covers it even if neither field changed
     * this request.
     */
    private function assertHasVideoSource(Request $request, bool $hasExisting, ?string $existingId = null): void
    {
        $hasNewVideo = $request->hasFile('videos');
        $hasYoutube = filled($request->input('link_youtube'));

        if (! $hasNewVideo && ! $hasYoutube && ! $hasExisting) {
            throw ValidationException::withMessages([
                'videos' => 'Isi salah satu: upload file video atau isi link YouTube.',
            ]);
        }
    }

    private function uniqueSlug(string $title, ?string $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'video';
        $slug = $base;
        $i = 1;

        while (
            WebVideo::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
