<?php

namespace App\Http\Controllers\Tagihan;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Tagihan;
use App\Models\TagihanCategory;
use App\Models\TagihanPelanggan;
use App\Models\TagihanPenerima;
use App\Models\TagihanReminderRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD App\Models\Tagihan (periode/invoice, mis. "Uang Sekolah Januari
 * 2026") -- dibuat MANUAL oleh admin (bukan cron), lihat migration
 * create_tagihan_table.php's docblock. store() sekaligus mengisi
 * App\Models\TagihanPenerima otomatis dari daftar langganan aktif
 * category-nya (tagihan_category_pelanggan) -- ini SATU-SATUNYA tempat
 * yang melakukan itu, jadi harus dibungkus transaction supaya "Tagihan
 * dibuat tapi penerimanya nggak ke-generate" tidak pernah terjadi
 * setengah-setengah.
 */
class TagihanController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $categoryId = $request->query('tagihan_category_id');

        $query = Tagihan::where('company_id', $company->id)
            ->withCount('penerima')
            ->with(['category:id,name', 'branchOffice:id,name']);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        if ($categoryId) {
            $query->where('tagihan_category_id', $categoryId);
        }

        $tagihanList = $query->latest()->paginate(15)->withQueryString()->onEachSide(1);

        $categories = TagihanCategory::where('company_id', $company->id)
            ->when($context->isLockedToBranch(), fn ($q) => $q->where('branch_office_id', $context->branchOffice?->id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('tagihan.tagihan.index', compact('tagihanList', 'categories', 'categoryId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        $categories = TagihanCategory::where('company_id', $context->company->id)
            ->where('status', 'active')
            ->when($context->isLockedToBranch(), fn ($q) => $q->where('branch_office_id', $context->branchOffice?->id))
            ->orderBy('name')
            ->get();

        return view('tagihan.tagihan.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $validated = $this->validator($request, $company)->validate();

        $category = TagihanCategory::where('company_id', $company->id)->findOrFail($validated['tagihan_category_id']);

        if ($context->isLockedToBranch() && $category->branch_office_id !== $context->branchOffice?->id) {
            abort(403);
        }

        $tagihan = DB::transaction(function () use ($validated, $category, $company, $request) {
            $tagihan = Tagihan::create([
                'company_id' => $company->id,
                'branch_office_id' => $category->branch_office_id,
                'tagihan_category_id' => $category->id,
                'name' => $validated['name'],
                'amount' => $validated['amount'],
                'due_date' => $validated['due_date'],
                // Toggle per-tagihan, nullable secara desain (lihat
                // docblock App\Models\Tagihan) -- default mengikuti
                // denda_enabled category, tapi admin boleh override
                // lewat checkbox di form create.
                'pakai_denda' => $request->boolean('pakai_denda', $category->denda_enabled),
                'status' => Tagihan::STATUS_ACTIVE,
            ]);

            $this->generatePenerimaFromLangganan($tagihan, $category);

            return $tagihan;
        });

        return redirect()
            ->route('tagihan.show', $tagihan->id)
            ->with('success', 'Tagihan berhasil dibuat. '.$tagihan->penerima()->count().' pelanggan otomatis ditagih dari daftar langganan kategori ini.');
    }

    public function show(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $tagihan = $this->findOrFail($context, $id);
        $tagihan->load([
            'category',
            'branchOffice:id,name,slug',
            'reminderRules',
            'penerima.pelanggan',
        ]);

        // Pelanggan branch yang sama TAPI belum jadi penerima di
        // Tagihan ini -- daftar kandidat buat form "Tambah Penerima
        // Manual" tanpa mengubah langganan category (lihat docblock
        // tagihan_category_pelanggan).
        $penerimaPelangganIds = $tagihan->penerima->pluck('tagihan_pelanggan_id');
        $availablePelanggan = TagihanPelanggan::where('company_id', $context->company->id)
            ->where('branch_office_id', $tagihan->branch_office_id)
            ->whereNotIn('id', $penerimaPelangganIds)
            ->orderBy('name')
            ->get();

        return view('tagihan.tagihan.show', compact('tagihan', 'availablePelanggan'));
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $tagihan = $this->findOrFail($context, $id);

        return view('tagihan.tagihan.edit', compact('tagihan'));
    }

    /**
     * SENGAJA cuma boleh ubah name/due_date/status -- amount & pakai_denda
     * adalah salinan terkunci begitu Tagihan dibuat (lihat docblock
     * migration create_tagihan_table.php soal "3 lapis salinan amount"),
     * supaya baris tagihan_penerima yang amount-nya sudah disalin dari
     * sini tidak pernah diam-diam berubah. Kalau nominalnya salah,
     * admin buat Tagihan baru, bukan edit yang sudah ada.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $tagihan = $this->findOrFail($context, $id);

        $validated = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'status' => ['required', 'in:'.Tagihan::STATUS_ACTIVE.','.Tagihan::STATUS_DIBATALKAN],
        ])->validate();

        $tagihan->update($validated);

        return redirect()
            ->route('tagihan.show', $tagihan->id)
            ->with('success', 'Tagihan berhasil diperbarui.');
    }

    /**
     * Tagihan yang sudah punya penerima yang LUNAS tidak boleh dihapus
     * (histori pembayaran riil) -- yang masih belum_bayar semua, boleh.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $tagihan = $this->findOrFail($context, $id);

        if ($tagihan->penerima()->where('status', TagihanPenerima::STATUS_LUNAS)->exists()) {
            return redirect()
                ->route('tagihan.show', $tagihan->id)
                ->with('error', 'Tagihan ini sudah ada yang lunas, tidak bisa dihapus.');
        }

        $tagihan->delete();

        return redirect()
            ->route('tagihan.index')
            ->with('success', 'Tagihan berhasil dihapus.');
    }

    /**
     * Tambah SATU baris TagihanPenerima manual -- tidak menyentuh
     * tagihan_category_pelanggan sama sekali (lihat docblock migration-
     * nya: dua hal ini sengaja lepas satu sama lain).
     */
    public function addPenerima(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $tagihan = $this->findOrFail($context, $id);

        $validated = Validator::make($request->all(), [
            'tagihan_pelanggan_id' => ['required', 'uuid'],
        ])->validate();

        $pelanggan = TagihanPelanggan::where('company_id', $context->company->id)
            ->where('branch_office_id', $tagihan->branch_office_id)
            ->findOrFail($validated['tagihan_pelanggan_id']);

        $exists = $tagihan->penerima()->where('tagihan_pelanggan_id', $pelanggan->id)->exists();

        if (! $exists) {
            TagihanPenerima::create([
                'tagihan_id' => $tagihan->id,
                'tagihan_pelanggan_id' => $pelanggan->id,
                'company_id' => $tagihan->company_id,
                'branch_office_id' => $tagihan->branch_office_id,
                'amount' => $tagihan->amount,
                'status' => TagihanPenerima::STATUS_BELUM_BAYAR,
            ]);
        }

        return redirect()
            ->route('tagihan.show', $tagihan->id)
            ->with('success', 'Penerima berhasil ditambahkan.');
    }

    /**
     * Penerima yang sudah LUNAS tidak boleh dihapus dari sini -- itu
     * histori pembayaran riil, bukan sekadar checklist. Batalkan lewat
     * App\Http\Controllers\Tagihan\TagihanPenerimaController::cancel()
     * kalau memang perlu, jangan dihapus.
     */
    public function removePenerima(Request $request, string $id, string $penerimaId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $tagihan = $this->findOrFail($context, $id);

        $penerima = $tagihan->penerima()->where('id', $penerimaId)->firstOrFail();

        if ($penerima->status === TagihanPenerima::STATUS_LUNAS) {
            return redirect()
                ->route('tagihan.show', $tagihan->id)
                ->with('error', 'Penerima ini sudah lunas, tidak bisa dihapus dari daftar.');
        }

        $penerima->delete();

        return redirect()
            ->route('tagihan.show', $tagihan->id)
            ->with('success', 'Penerima berhasil dihapus dari Tagihan ini.');
    }

    public function addReminderRule(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $tagihan = $this->findOrFail($context, $id);

        $validated = Validator::make($request->all(), [
            'remind_value' => ['required', 'integer', 'min:0'],
            'remind_unit' => ['required', 'in:'.implode(',', TagihanReminderRule::UNITS)],
        ])->validate();

        $tagihan->reminderRules()->create($validated);

        return redirect()
            ->route('tagihan.show', $tagihan->id)
            ->with('success', 'Aturan pengingat berhasil ditambahkan.');
    }

    public function removeReminderRule(Request $request, string $id, string $ruleId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $tagihan = $this->findOrFail($context, $id);

        $tagihan->reminderRules()->where('id', $ruleId)->firstOrFail()->delete();

        return redirect()
            ->route('tagihan.show', $tagihan->id)
            ->with('success', 'Aturan pengingat berhasil dihapus.');
    }

    /**
     * Disalin dari tagihan_category_pelanggan yang status-nya masih
     * 'active' pada SAAT Tagihan ini dibuat -- perubahan langganan
     * SETELAHNYA tidak menyentuh Tagihan yang sudah ada (harus
     * ditambah/dihapus manual lewat addPenerima()/removePenerima() di
     * atas), sama seperti dijelaskan di docblock migration-nya.
     * nominal_override, kalau diisi di langganannya, menang atas
     * amount default Tagihan.
     */
    private function generatePenerimaFromLangganan(Tagihan $tagihan, TagihanCategory $category): void
    {
        $langganan = $category->categoryPelanggan()->where('status', 'active')->with('pelanggan')->get();

        foreach ($langganan as $item) {
            if (! $item->pelanggan || $item->pelanggan->status !== 'active') {
                continue;
            }

            TagihanPenerima::create([
                'tagihan_id' => $tagihan->id,
                'tagihan_pelanggan_id' => $item->tagihan_pelanggan_id,
                'company_id' => $tagihan->company_id,
                'branch_office_id' => $tagihan->branch_office_id,
                'amount' => $item->nominal_override ?? $tagihan->amount,
                'status' => TagihanPenerima::STATUS_BELUM_BAYAR,
            ]);
        }
    }

    private function findOrFail($context, string $id): Tagihan
    {
        $query = Tagihan::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }

    private function validator(Request $request, Company $company)
    {
        return Validator::make($request->all(), [
            'tagihan_category_id' => [
                'required', 'uuid',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! TagihanCategory::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Kategori tagihan tidak valid.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
        ]);
    }
}
