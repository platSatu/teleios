<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\WebPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin > Web > Halaman: halaman dinamis fe-konexa (/page/{slug}).
 * Tipe Dokumen = isi Markdown sederhana; tipe Landing = section (dikelola
 * HomeSectionController dengan web_page_id). Tipe dikunci setelah dibuat.
 */
class PageController extends Controller
{
    private const IMAGE_SUBDIRECTORY = 'pages';

    public function index(Request $request): View
    {
        $search = $request->string('search')->value();

        $pages = WebPage::query()
            ->withCount('sections')
            ->when($search, fn ($query) => $query->where(fn ($q) => $q->where('title', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->orderBy('title')
            ->paginate(30)
            ->withQueryString();

        return view('superadmin.web.pages.index', compact('pages', 'search'));
    }

    public function create(): View
    {
        return view('superadmin.web.pages.form', ['page' => new WebPage(['type' => 'document', 'status' => 'active'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, new WebPage());
        $page = CrudAdmin::store(WebPage::class, $data);

        return redirect()
            ->route('web.pages.edit', $page->id)
            ->with('success', $page->isLanding() ? 'Halaman dibuat. Sekarang tambahkan section-nya di bawah.' : 'Halaman berhasil dibuat.');
    }

    public function edit(string $id): View
    {
        $page = CrudAdmin::find(WebPage::class, $id);
        $sections = $page->isLanding() ? $page->sections()->withCount('items')->get() : collect();

        return view('superadmin.web.pages.form', compact('page', 'sections'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $page = CrudAdmin::find(WebPage::class, $id);
        $data = $this->validated($request, $page);

        CrudAdmin::update(WebPage::class, $id, $data, beforeUpdate: function (WebPage $model, array $data) {
            foreach (['hero_image', 'meta_image'] as $field) {
                if (isset($data[$field]) && $model->{$field} && $model->{$field} !== $data[$field]) {
                    WebImageUploader::delete($model->{$field});
                }
            }

            return $data;
        });

        return redirect()->route('web.pages.edit', $id)->with('success', 'Halaman berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        CrudAdmin::delete(WebPage::class, $id, fn (WebPage $model) => $model->deleteFiles());

        return redirect()->route('web.pages.index')->with('success', 'Halaman berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, WebPage $page): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('title')))]);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('web_pages', 'slug')->ignore($page->id)],
            'type' => [$page->exists ? 'prohibited' : 'required', Rule::in(array_keys(WebPage::TYPES))],
            'subtitle' => ['nullable', 'string', 'max:1000'],
            'hero_image' => ['nullable', 'image', 'max:4096'],
            'content' => ['nullable', 'string', 'max:100000'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'meta_image' => ['nullable', 'image', 'max:4096'],
            'navbar_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'footer_group' => ['nullable', 'string', 'max:100', 'required_if:show_in_footer,1'],
            'footer_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ], [
            'slug.unique' => 'Alamat halaman ini sudah dipakai halaman lain.',
            'slug.regex' => 'Alamat halaman hanya huruf kecil, angka, dan tanda "-".',
            'footer_group.required_if' => 'Isi nama kolom footer, mis. "Perusahaan" atau "Bantuan".',
        ]);

        $data['show_in_navbar'] = $request->boolean('show_in_navbar');
        $data['show_in_footer'] = $request->boolean('show_in_footer');
        $data['navbar_order'] = (int) ($data['navbar_order'] ?? 0);
        $data['footer_order'] = (int) ($data['footer_order'] ?? 0);

        if (($data['type'] ?? $page->type) !== 'document') {
            unset($data['content']);
        }

        foreach (['hero_image' => 1920, 'meta_image' => 1200] as $field => $maxWidth) {
            unset($data[$field]);

            if ($request->hasFile($field)) {
                $data[$field] = WebImageUploader::upload($request->file($field), self::IMAGE_SUBDIRECTORY, $maxWidth);
            }
        }

        return $data;
    }
}
