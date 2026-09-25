<?php

namespace App\Http\Controllers\Superadmin\Web;

use App\Helpers\CrudAdmin;
use App\Helpers\WebImageUploader;
use App\Http\Controllers\Controller;
use App\Models\WebHomeSection;
use App\Models\WebHomeSectionItem;
use App\Support\SortOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Item section tambahan beranda (Grid Ikon, Kartu, Logo, Statistik,
 * Testimoni). Field yang berlaku per tipe: WebHomeSection::ITEM_FIELDS.
 */
class HomeSectionItemController extends Controller
{
    private const IMAGE_SUBDIRECTORY = 'home-sections/items';

    public function create(string $section): View
    {
        $section = $this->sectionWithItems($section);

        return view('superadmin.web.home-sections.item-form', ['section' => $section, 'item' => new WebHomeSectionItem()]);
    }

    public function store(Request $request, string $section): RedirectResponse
    {
        $section = $this->sectionWithItems($section);
        $data = $this->validated($request, $section, new WebHomeSectionItem()) + [
            'web_home_section_id' => $section->id,
            'sort_order' => SortOrder::next(WebHomeSectionItem::where('web_home_section_id', $section->id)),
        ];

        CrudAdmin::store(WebHomeSectionItem::class, $data);

        return redirect()->route('web.home-sections.edit', $section->id)->with('success', 'Item berhasil ditambahkan.');
    }

    public function edit(string $id): View
    {
        $item = CrudAdmin::find(WebHomeSectionItem::class, $id, ['section']);

        return view('superadmin.web.home-sections.item-form', ['section' => $item->section, 'item' => $item]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $item = CrudAdmin::find(WebHomeSectionItem::class, $id, ['section']);
        $data = $this->validated($request, $item->section, $item);

        CrudAdmin::update(WebHomeSectionItem::class, $id, $data, beforeUpdate: function (WebHomeSectionItem $model, array $data) {
            if (isset($data['image']) && $model->image && $model->image !== $data['image']) {
                WebImageUploader::delete($model->image);
            }

            return $data;
        });

        return redirect()->route('web.home-sections.edit', $item->web_home_section_id)->with('success', 'Item berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $sectionId = CrudAdmin::find(WebHomeSectionItem::class, $id)->web_home_section_id;

        CrudAdmin::delete(WebHomeSectionItem::class, $id, fn (WebHomeSectionItem $model) => WebImageUploader::delete($model->image));

        return redirect()->route('web.home-sections.edit', $sectionId)->with('success', 'Item berhasil dihapus.');
    }

    public function move(string $id, string $direction): RedirectResponse
    {
        $item = WebHomeSectionItem::findOrFail($id);

        SortOrder::move($item, $direction, WebHomeSectionItem::where('web_home_section_id', $item->web_home_section_id));

        return back();
    }

    private function sectionWithItems(string $id): WebHomeSection
    {
        $section = CrudAdmin::find(WebHomeSection::class, $id);
        abort_unless($section->hasItems(), 404);

        return $section;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, WebHomeSection $section, WebHomeSectionItem $item): array
    {
        $base = [
            'icon' => ['regex:/^[a-z0-9-]{1,60}$/'],
            'image' => ['image', 'max:4096'],
            'title' => ['string', 'max:255'],
            'description' => ['string', 'max:2000'],
            'value' => ['string', 'max:100'],
            'link_text' => ['string', 'max:60'],
            'link_url' => ['string', 'max:500', HomeSectionController::SAFE_LINK],
        ];

        $rules = [];

        foreach ($section->itemFields() as $field => [, $required]) {
            $mustFill = $required && ! ($field === 'image' && $item->image);
            $rules[$field] = array_merge([$mustFill ? 'required' : 'nullable'], $base[$field]);
        }

        $data = $request->validate($rules, [
            'icon.regex' => 'Nama ikon hanya huruf kecil, angka, dan tanda "-" (mis. shield-check).',
            'link_url.regex' => 'Link harus diawali https://, /, #, mailto: atau tel:.',
        ]);

        if ($section->type === 'icon_grid' && empty($data['icon']) && ! $request->hasFile('image') && ! $item->image) {
            throw ValidationException::withMessages(['icon' => 'Isi nama ikon atau upload gambar ikon.']);
        }

        unset($data['image']);

        if ($request->hasFile('image') && isset($rules['image'])) {
            $data['image'] = $section->type === 'testimonials'
                ? WebImageUploader::uploadCover($request->file('image'), self::IMAGE_SUBDIRECTORY, 200, 200)
                : WebImageUploader::upload($request->file('image'), self::IMAGE_SUBDIRECTORY, $section->type === 'cards' ? 1200 : 600);
        }

        return $data;
    }
}
