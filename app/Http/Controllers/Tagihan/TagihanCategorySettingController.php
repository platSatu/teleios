<?php

namespace App\Http\Controllers\Tagihan;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\TagihanCategory;
use App\Models\TagihanCategoryReminderRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Halaman "Setting Tagihan" per App\Models\TagihanCategory -- 23
 * September 2026 redesign (lihat migration
 * 2026_09_23_..._simplify_tagihan_category_table.php's docblock).
 * Satu view 3-tab (resources/views/tagihan/category/setting.blade.php):
 *
 * Tab 1 "Denda" -- pilih salah satu dari 3 App\Models\TagihanCategory::
 * DENDA_MODE_* dan isi field-nya, disimpan langsung ke kolom denda_*
 * milik category (bukan tabel tersendiri seperti App\Models\TagihanDendaTier
 * yang lama).
 *
 * Tab 2 "Pengingat" -- CRUD baris App\Models\TagihanCategoryReminderRule,
 * template yang disalin ke App\Models\TagihanReminderRule tiap kali
 * Tagihan baru dibuat di bawah category ini (lihat TagihanController::store()).
 *
 * Tab 3 "Invoice & Notifikasi" -- invoice_prefix + notifikasi_email_aktif.
 */
class TagihanCategorySettingController extends Controller
{
    use ResolvesCompanyContext;

    public function edit(Request $request, string $tagihanCategory): View
    {
        $context = $this->companyContext($request);

        $category = $this->findOrFail($context, $tagihanCategory);
        $category->load(['reminderRuleTemplates' => fn ($q) => $q->orderBy('remind_value')]);

        return view('tagihan.category.setting', [
            'category' => $category,
        ]);
    }

    /**
     * Simpan mode denda -- validasi field wajib beda-beda per mode
     * (lihat rules()), field yang tidak relevan buat mode terpilih
     * SENGAJA tidak dipaksa null di sini (dibiarkan seperti masukan
     * terakhir user di DB) supaya kalau admin gonta-ganti mode
     * bolak-balik, nilai yang pernah diisi tidak hilang.
     */
    public function updateDenda(Request $request, string $tagihanCategory): RedirectResponse
    {
        $context = $this->companyContext($request);
        $category = $this->findOrFail($context, $tagihanCategory);

        $validator = $this->dendaValidator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('tagihan.category.setting', $category->id)
                ->withErrors($validator, 'denda')
                ->withInput();
        }

        $validated = $validator->validated();

        $category->update([
            'denda_mode' => $validated['denda_mode'],
            'denda_flat_amount' => $validated['denda_flat_amount'] ?? null,
            'denda_persen' => $validated['denda_persen'] ?? null,
            'denda_persen_frekuensi' => $validated['denda_persen_frekuensi'] ?? null,
            'denda_persen_sampai_hari' => $validated['denda_persen_sampai_hari'] ?? null,
        ]);

        return redirect()
            ->route('tagihan.category.setting', $category->id)
            ->with('success', 'Aturan denda berhasil disimpan.');
    }

    public function addReminderRule(Request $request, string $tagihanCategory): RedirectResponse
    {
        $context = $this->companyContext($request);
        $category = $this->findOrFail($context, $tagihanCategory);

        $validated = $request->validate([
            'remind_value' => ['required', 'integer', 'min:1', 'max:999'],
            'remind_unit' => ['required', 'in:hours,days'],
        ]);

        $category->reminderRuleTemplates()->create($validated);

        return redirect()
            ->route('tagihan.category.setting', $category->id)
            ->with('success', 'Aturan pengingat berhasil ditambahkan.');
    }

    public function removeReminderRule(Request $request, string $tagihanCategory, string $ruleId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $category = $this->findOrFail($context, $tagihanCategory);

        TagihanCategoryReminderRule::where('tagihan_category_id', $category->id)
            ->where('id', $ruleId)
            ->delete();

        return redirect()
            ->route('tagihan.category.setting', $category->id)
            ->with('success', 'Aturan pengingat berhasil dihapus.');
    }

    public function updateInvoice(Request $request, string $tagihanCategory): RedirectResponse
    {
        $context = $this->companyContext($request);
        $category = $this->findOrFail($context, $tagihanCategory);

        $validated = $request->validate([
            'invoice_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'notifikasi_email_aktif' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'invoice_prefix' => $validated['invoice_prefix'] ?? null,
            'notifikasi_email_aktif' => $request->boolean('notifikasi_email_aktif'),
        ]);

        return redirect()
            ->route('tagihan.category.setting', $category->id)
            ->with('success', 'Setting invoice & notifikasi berhasil disimpan.');
    }

    private function findOrFail($context, string $id): TagihanCategory
    {
        $query = TagihanCategory::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }

    private function dendaValidator(Request $request)
    {
        return Validator::make($request->all(), [
            'denda_mode' => ['required', 'in:flat,persentase,persentase_flat'],
            'denda_flat_amount' => ['required_if:denda_mode,flat', 'nullable', 'numeric', 'min:0'],
            'denda_persen' => ['required_if:denda_mode,persentase,persentase_flat', 'nullable', 'numeric', 'min:0', 'max:100'],
            'denda_persen_frekuensi' => ['required_if:denda_mode,persentase', 'nullable', 'in:per_hari,per_bulan'],
            'denda_persen_sampai_hari' => ['required_if:denda_mode,persentase_flat', 'nullable', 'integer', 'min:1', 'max:365'],
        ]);
    }
}
