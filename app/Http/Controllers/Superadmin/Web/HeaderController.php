<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebFileUploader;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebHeader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Superadmin CRUD for header/hero slides (App\Models\WebHeader). Same
 * shape as Superadmin\Web\VideoController: CrudAdmin for data access,
 * uploads through WebFileUploader (videos)/WebImageUploader
 * (background_images, thumbnail_images), old files cleaned up on
 * replace/delete.
 *
 * The video-vs-image mutual exclusivity lives here, not in the DB: a
 * slide's background_type says which of videos/background_images is
 * actually used, and assertHasBackgroundSource() makes sure the field
 * matching that choice is actually filled (either just-uploaded or
 * already on record) — same "checked after validate(), not a single
 * column rule" reasoning VideoController uses for
 * assertHasVideoSource().
 */
class HeaderController extends Controller
{
    private const BACKGROUND_IMAGE_SUBDIRECTORY = 'headers';
    private const THUMB_SUBDIRECTORY = 'headers/thumbnails';
    private const VIDEO_FILE_SUBDIRECTORY = 'headers';

    public function index(Request $request): View
    {
        $headers = CrudAdmin::getAll(
            modelClass: WebHeader::class,
            relations: ['user'],
            search: $request->string('search')->value() ?: null,
            searchFields: ['text', 'descriptions'],
        );

        return view('superadmin.web.headers.index', compact('headers'));
    }

    public function create(): View
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.headers.create', compact('users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->assertHasBackgroundSource(
            $request,
            backgroundType: $validated['background_type'],
            hasExistingVideo: false,
            hasExistingImage: false,
        );

        if ($request->hasFile('videos')) {
            $validated['videos'] = WebFileUploader::upload($request->file('videos'), self::VIDEO_FILE_SUBDIRECTORY);
        }

        if ($request->hasFile('background_images')) {
            $validated['background_images'] = WebImageUploader::upload($request->file('background_images'), self::BACKGROUND_IMAGE_SUBDIRECTORY);
        }

        if ($request->hasFile('thumbnail_images')) {
            $validated['thumbnail_images'] = WebImageUploader::upload($request->file('thumbnail_images'), self::THUMB_SUBDIRECTORY);
        }

        if ($request->hasFile('thumbnail_background_images')) {
            $validated['thumbnail_background_images'] = WebImageUploader::upload($request->file('thumbnail_background_images'), self::THUMB_SUBDIRECTORY);
        }

        CrudAdmin::store(WebHeader::class, $validated);

        return redirect()
            ->route('web.headers.index')
            ->with('success', 'Header berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $header = CrudAdmin::find(WebHeader::class, $id);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('superadmin.web.headers.edit', compact('header', 'users'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $existing = CrudAdmin::find(WebHeader::class, $id);

        $validated = $this->validated($request);
        $this->assertHasBackgroundSource(
            $request,
            backgroundType: $validated['background_type'],
            hasExistingVideo: (bool) $existing->videos,
            hasExistingImage: (bool) $existing->background_images,
        );

        if ($request->hasFile('videos')) {
            $validated['videos'] = WebFileUploader::upload($request->file('videos'), self::VIDEO_FILE_SUBDIRECTORY);
        }

        if ($request->hasFile('background_images')) {
            $validated['background_images'] = WebImageUploader::upload($request->file('background_images'), self::BACKGROUND_IMAGE_SUBDIRECTORY);
        }

        if ($request->hasFile('thumbnail_images')) {
            $validated['thumbnail_images'] = WebImageUploader::upload($request->file('thumbnail_images'), self::THUMB_SUBDIRECTORY);
        }

        if ($request->hasFile('thumbnail_background_images')) {
            $validated['thumbnail_background_images'] = WebImageUploader::upload($request->file('thumbnail_background_images'), self::THUMB_SUBDIRECTORY);
        }

        CrudAdmin::update(
            WebHeader::class,
            $id,
            $validated,
            beforeUpdate: function ($model, $data) {
                if (array_key_exists('videos', $data) && $model->videos && $model->videos !== $data['videos']) {
                    WebFileUploader::delete($model->videos);
                }

                if (array_key_exists('background_images', $data) && $model->background_images && $model->background_images !== $data['background_images']) {
                    WebImageUploader::delete($model->background_images);
                }

                if (array_key_exists('thumbnail_images', $data) && $model->thumbnail_images && $model->thumbnail_images !== $data['thumbnail_images']) {
                    WebImageUploader::delete($model->thumbnail_images);
                }

                if (array_key_exists('thumbnail_background_images', $data) && $model->thumbnail_background_images && $model->thumbnail_background_images !== $data['thumbnail_background_images']) {
                    WebImageUploader::delete($model->thumbnail_background_images);
                }

                return $data;
            }
        );

        return redirect()
            ->route('web.headers.index')
            ->with('success', 'Header berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebHeader::class, $id, function ($model) {
            WebFileUploader::delete($model->videos);
            WebImageUploader::delete($model->background_images);
            WebImageUploader::delete($model->thumbnail_images);
            WebImageUploader::delete($model->thumbnail_background_images);
        });

        return redirect()
            ->route('web.headers.index')
            ->with('success', 'Header berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'background_type' => ['required', 'in:image,video'],
            'videos' => ['nullable', 'file', 'mimes:mp4,mov,avi,wmv,mkv,webm', 'max:102400'],
            'background_images' => ['nullable', 'image', 'max:4096'],
            'thumbnail_images' => ['nullable', 'image', 'max:4096'],
            'thumbnail_background_images' => ['nullable', 'image', 'max:4096'],
            'text' => ['nullable', 'string', 'max:255'],
            'descriptions' => ['nullable', 'string'],
            'color_headline' => ['nullable', 'string', 'max:20'],
            'color_description' => ['nullable', 'string', 'max:20'],
            'button_action' => ['required', 'in:active,inactive'],
            'button_text' => ['nullable', 'required_if:button_action,active', 'string', 'max:255'],
            'button_link' => ['nullable', 'required_if:button_action,active', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
        ]);
    }

    /**
     * A slide's chosen background_type needs an actual source behind it
     * — either a just-uploaded file, or one already on record (on
     * update). Checked here rather than as a single validate() rule
     * since "the field matching background_type must be filled" needs
     * to see both the type and the file/existing-record state at once.
     */
    private function assertHasBackgroundSource(
        Request $request,
        string $backgroundType,
        bool $hasExistingVideo,
        bool $hasExistingImage,
    ): void {
        if ($backgroundType === 'video') {
            $hasVideo = $request->hasFile('videos') || $hasExistingVideo;

            if (! $hasVideo) {
                throw ValidationException::withMessages([
                    'videos' => 'Upload video wajib diisi jika Tipe Background = Video.',
                ]);
            }

            return;
        }

        $hasImage = $request->hasFile('background_images') || $hasExistingImage;

        if (! $hasImage) {
            throw ValidationException::withMessages([
                'background_images' => 'Upload gambar wajib diisi jika Tipe Background = Gambar.',
            ]);
        }
    }
}
