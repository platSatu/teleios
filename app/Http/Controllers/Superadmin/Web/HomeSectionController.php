<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebFileUploader;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\WebCategoryArticle;
use App\Models\WebHomeSection;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Superadmin > Web > Susunan Beranda: section-section beranda fe-konexa
 * (urutan, tampil/sembunyi, bingkai, isi). Tipe section & field yang
 * berlaku per tipe ada di App\Models\WebHomeSection::TYPES; item section
 * tambahan dikelola HomeSectionItemController. Ditayangkan publik lewat
 * App\Http\Controllers\Api\Frontend\HomeSectionController.
 */
class HomeSectionController extends Controller
{
    /** Link tombol hanya boleh http(s), path lokal, anchor, mailto, tel (bukan javascript:). */
    public const SAFE_LINK = 'regex:/^(https?:\/\/|\/(?!\/)|#|mailto:|tel:)/i';

    private const FILE_SUBDIRECTORY = 'home-sections';

    public function index(): View
    {
        $sections = WebHomeSection::query()->withCount('items')->orderBy('sort_order')->orderBy('created_at')->get();
        $usedBuiltins = $sections->filter(fn (WebHomeSection $section) => $section->isBuiltin())->pluck('type')->all();

        return view('superadmin.web.home-sections.index', compact('sections', 'usedBuiltins'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $type = (string) $request->query('type');
        abort_unless(isset(WebHomeSection::TYPES[$type]), 404);

        if ($this->builtinTaken($type)) {
            return redirect()->route('web.home-sections.index')->with('error', 'Section '.WebHomeSection::TYPES[$type]['label'].' sudah ada di beranda.');
        }

        return view('superadmin.web.home-sections.form', [
            'section' => new WebHomeSection(['type' => $type, 'status' => 'active', 'background_type' => 'none', 'text_align' => 'center', 'media_position' => 'right', 'item_limit' => 3]),
            'categories' => WebCategoryArticle::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = (string) $request->input('type');
        abort_unless(isset(WebHomeSection::TYPES[$type]), 422);

        if ($this->builtinTaken($type)) {
            return redirect()->route('web.home-sections.index')->with('error', 'Section '.WebHomeSection::TYPES[$type]['label'].' sudah ada di beranda.');
        }

        $section = new WebHomeSection(['type' => $type]);
        $data = $this->validated($request, $section) + [
            'type' => $type,
            'sort_order' => SortOrder::next(WebHomeSection::query()),
        ];

        $created = CrudAdmin::store(WebHomeSection::class, $data);

        return redirect()
            ->route('web.home-sections.edit', $created->id)
            ->with('success', $section->hasItems() ? 'Section dibuat. Sekarang tambahkan item-nya di bawah.' : 'Section berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        return view('superadmin.web.home-sections.form', [
            'section' => CrudAdmin::find(WebHomeSection::class, $id, ['items']),
            'categories' => WebCategoryArticle::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $section = CrudAdmin::find(WebHomeSection::class, $id);
        $data = $this->validated($request, $section);

        CrudAdmin::update(WebHomeSection::class, $id, $data, beforeUpdate: function (WebHomeSection $model, array $data) {
            foreach (['background_image', 'media_image'] as $field) {
                if (isset($data[$field]) && $model->{$field} && $model->{$field} !== $data[$field]) {
                    WebImageUploader::delete($model->{$field});
                }
            }

            if (isset($data['background_video']) && $model->background_video && $model->background_video !== $data['background_video']) {
                WebFileUploader::delete($model->background_video);
            }

            return $data;
        });

        return redirect()->route('web.home-sections.edit', $id)->with('success', 'Section berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebHomeSection::class, $id, function (WebHomeSection $model) {
            WebImageUploader::delete($model->background_image);
            WebImageUploader::delete($model->media_image);
            WebFileUploader::delete($model->background_video);
            $model->items()->pluck('image')->each(fn (?string $path) => WebImageUploader::delete($path));
        });

        return redirect()->route('web.home-sections.index')->with('success', 'Section berhasil dihapus.');
    }

    public function move(string $id, string $direction): RedirectResponse
    {
        SortOrder::move(WebHomeSection::findOrFail($id), $direction, WebHomeSection::query());

        return back();
    }

    private function builtinTaken(string $type): bool
    {
        return (WebHomeSection::TYPES[$type]['builtin'] ?? false)
            && WebHomeSection::where('type', $type)->exists();
    }

    /**
     * Aturan validasi & upload sesuai tipe section. Field yang tidak
     * berlaku untuk tipe itu tidak pernah ikut tersimpan.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, WebHomeSection $section): array
    {
        $rules = ['status' => ['required', 'in:active,inactive']];

        if ($section->hasFrame()) {
            $rules += [
                'title' => ['nullable', 'string', 'max:255'],
                'subtitle' => ['nullable', 'string', 'max:1000'],
                'text_align' => ['required', 'in:center,left'],
                'background_type' => ['required', 'in:'.($section->allowsVideo() ? 'none,color,image,video' : 'none,color,image')],
                'background_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'background_image' => ['nullable', 'image', 'max:4096'],
                'background_video' => ['nullable', 'file', 'mimes:mp4,webm', 'max:51200'],
                'cta_text' => ['nullable', 'string', 'max:60', 'required_with:cta_link'],
                'cta_link' => ['nullable', 'string', 'max:500', self::SAFE_LINK, 'required_with:cta_text'],
                'cta2_text' => ['nullable', 'string', 'max:60', 'required_with:cta2_link'],
                'cta2_link' => ['nullable', 'string', 'max:500', self::SAFE_LINK, 'required_with:cta2_text'],
            ];
        }

        if ($section->typeConfig('content')) {
            $rules += [
                'content' => ['nullable', 'string', 'max:10000'],
                'media_image' => ['nullable', 'image', 'max:4096'],
                'media_position' => ['required', 'in:left,right'],
            ];
        }

        if ($section->typeConfig('articles')) {
            $rules += [
                'item_limit' => ['required', 'integer', 'min:1', 'max:12'],
                'web_category_article_id' => ['nullable', 'uuid', 'exists:web_category_articles,id'],
            ];
        }

        $data = $request->validate($rules, [
            'cta_link.regex' => 'Link tombol harus diawali https://, /, #, mailto: atau tel:.',
            'cta2_link.regex' => 'Link tombol harus diawali https://, /, #, mailto: atau tel:.',
        ]);

        $backgroundType = $data['background_type'] ?? 'none';

        if ($backgroundType === 'image' && ! $request->hasFile('background_image') && ! $section->background_image) {
            throw ValidationException::withMessages(['background_image' => 'Upload gambar background, atau pilih jenis background lain.']);
        }

        if ($backgroundType === 'video' && ! $request->hasFile('background_video') && ! $section->background_video) {
            throw ValidationException::withMessages(['background_video' => 'Upload video background, atau pilih jenis background lain.']);
        }

        foreach (['background_image' => 1920, 'media_image' => 1600] as $field => $maxWidth) {
            unset($data[$field]);

            if ($request->hasFile($field) && array_key_exists($field, $rules)) {
                $data[$field] = WebImageUploader::upload($request->file($field), self::FILE_SUBDIRECTORY, $maxWidth);
            }
        }

        unset($data['background_video']);

        if ($request->hasFile('background_video') && array_key_exists('background_video', $rules)) {
            $data['background_video'] = WebFileUploader::upload($request->file('background_video'), self::FILE_SUBDIRECTORY);
        }

        return $data;
    }
}
