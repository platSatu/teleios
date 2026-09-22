<?php

namespace App\Http\Controllers\Tagihan;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\TagihanCategory;
use App\Models\TagihanDendaTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Aturan denda bertingkat (mis. "H+1 s/d H+5: 0.5%/hari", "> H+5: flat
 * Rp1.000") milik satu App\Models\TagihanCategory -- selalu diakses
 * nested di bawah category-nya (lihat routes/web.php's
 * 'category/{tagihanCategory}/denda-tier'), tidak ada index/show
 * sendiri karena selalu ditampilkan sebagai bagian dari halaman edit
 * category (resources/views/tagihan/category/edit.blade.php).
 */
class TagihanDendaTierController extends Controller
{
    use ResolvesCompanyContext;

    public function store(Request $request, string $tagihanCategory): RedirectResponse
    {
        $context = $this->companyContext($request);
        $category = $this->findCategoryOrFail($context, $tagihanCategory);

        $validated = $this->validator($request)->validate();

        TagihanDendaTier::create([
            'tagihan_category_id' => $category->id,
            'urutan' => $category->dendaTiers()->max('urutan') + 1,
            'mulai_hari_ke' => $validated['mulai_hari_ke'],
            'sampai_hari_ke' => $validated['sampai_hari_ke'] ?? null,
            'tipe' => $validated['tipe'],
            'nilai' => $validated['nilai'],
            'frekuensi_flat' => $validated['tipe'] === TagihanDendaTier::TIPE_FLAT
                ? ($validated['frekuensi_flat'] ?? TagihanDendaTier::FREKUENSI_SEKALI)
                : null,
        ]);

        return redirect()
            ->route('tagihan.category.edit', $category->id)
            ->with('success', 'Aturan denda berhasil ditambahkan.');
    }

    public function update(Request $request, string $tagihanCategory, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $category = $this->findCategoryOrFail($context, $tagihanCategory);

        $tier = $category->dendaTiers()->where('id', $id)->firstOrFail();

        $validated = $this->validator($request)->validate();

        $tier->update([
            'mulai_hari_ke' => $validated['mulai_hari_ke'],
            'sampai_hari_ke' => $validated['sampai_hari_ke'] ?? null,
            'tipe' => $validated['tipe'],
            'nilai' => $validated['nilai'],
            'frekuensi_flat' => $validated['tipe'] === TagihanDendaTier::TIPE_FLAT
                ? ($validated['frekuensi_flat'] ?? TagihanDendaTier::FREKUENSI_SEKALI)
                : null,
        ]);

        return redirect()
            ->route('tagihan.category.edit', $category->id)
            ->with('success', 'Aturan denda berhasil diperbarui.');
    }

    public function destroy(Request $request, string $tagihanCategory, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $category = $this->findCategoryOrFail($context, $tagihanCategory);

        $category->dendaTiers()->where('id', $id)->firstOrFail()->delete();

        return redirect()
            ->route('tagihan.category.edit', $category->id)
            ->with('success', 'Aturan denda berhasil dihapus.');
    }

    private function findCategoryOrFail($context, string $id): TagihanCategory
    {
        $query = TagihanCategory::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'mulai_hari_ke' => ['required', 'integer', 'min:0'],
            'sampai_hari_ke' => ['nullable', 'integer', 'gte:mulai_hari_ke'],
            'tipe' => ['required', 'in:'.implode(',', TagihanDendaTier::TIPE_LIST)],
            'nilai' => ['required', 'numeric', 'min:0'],
            'frekuensi_flat' => ['nullable', 'in:'.TagihanDendaTier::FREKUENSI_PER_HARI.','.TagihanDendaTier::FREKUENSI_SEKALI],
        ]);
    }
}
