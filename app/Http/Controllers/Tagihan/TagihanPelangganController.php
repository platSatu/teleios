<?php

namespace App\Http\Controllers\Tagihan;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\TagihanCategory;
use App\Models\TagihanCategoryPelanggan;
use App\Models\TagihanPelanggan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD kontak yang ditagih (App\Models\TagihanPelanggan) + checklist
 * "langganan" category (App\Models\TagihanCategoryPelanggan) yang
 * dipakai buat auto-isi App\Models\TagihanPenerima saat
 * App\Http\Controllers\Tagihan\TagihanController::store() membuat
 * Tagihan baru. Pola company/branch-scoping sama dengan
 * TagihanCategoryController.
 */
class TagihanPelangganController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $request->query('branch_office_id');

        $query = TagihanPelanggan::where('company_id', $company->id)
            ->with('branchOffice:id,name');

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        } elseif ($branchOfficeId) {
            $query->where('branch_office_id', $branchOfficeId);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->string('search').'%')
                    ->orWhere('phone_number', 'like', '%'.$request->string('search').'%');
            });
        }

        $pelanggan = $query->latest()->paginate(15)->withQueryString()->onEachSide(1);

        return view('tagihan.pelanggan.index', compact('pelanggan', 'branchOfficeId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('tagihan.pelanggan.create', [
            'pelanggan' => null,
            'selectedBranchOfficeId' => $request->query('branch_office_id'),
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('tagihan.pelanggan.create', array_filter(['branch_office_id' => $request->input('branch_office_id')]))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        TagihanPelanggan::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'],
            'name' => $validated['name'],
            'phone_number' => $validated['phone_number'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'kirim_link_otomatis' => $request->boolean('kirim_link_otomatis'),
        ]);

        return redirect()
            ->route('tagihan.pelanggan.index', ['branch_office_id' => $validated['branch_office_id']])
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $pelanggan = $this->findOrFail($context, $id);
        $pelanggan->load('categoryPelanggan');

        // Semua category tagihan di branch pelanggan ini -- dipakai
        // membangun checklist "langganan category apa saja" di halaman
        // edit, dengan status ceklis diambil dari categoryPelanggan yang
        // sudah dimuat di atas.
        $categories = TagihanCategory::where('company_id', $context->company->id)
            ->where('branch_office_id', $pelanggan->branch_office_id)
            ->orderBy('name')
            ->get();

        return view('tagihan.pelanggan.edit', [
            'pelanggan' => $pelanggan,
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $pelanggan = $this->findOrFail($context, $id);

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('tagihan.pelanggan.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $pelanggan->update([
            'branch_office_id' => $validated['branch_office_id'],
            'name' => $validated['name'],
            'phone_number' => $validated['phone_number'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'kirim_link_otomatis' => $request->boolean('kirim_link_otomatis'),
        ]);

        return redirect()
            ->route('tagihan.pelanggan.index', ['branch_office_id' => $pelanggan->branch_office_id])
            ->with('success', 'Pelanggan berhasil diperbarui.');
    }

    /**
     * Pelanggan yang masih punya baris tagihan_penerima (pernah/sedang
     * ditagih) TIDAK dihapus -- histori pembayaran harus tetap utuh.
     * restrictOnDelete() di migration tagihan_penerima menegakkan ini
     * juga di level DB.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $pelanggan = $this->findOrFail($context, $id);
        $branchOfficeId = $pelanggan->branch_office_id;

        if ($pelanggan->tagihanPenerima()->exists()) {
            return redirect()
                ->route('tagihan.pelanggan.index', ['branch_office_id' => $branchOfficeId])
                ->with('error', 'Pelanggan ini masih punya riwayat tagihan, tidak bisa dihapus.');
        }

        $pelanggan->delete();

        return redirect()
            ->route('tagihan.pelanggan.index', ['branch_office_id' => $branchOfficeId])
            ->with('success', 'Pelanggan berhasil dihapus.');
    }

    /**
     * Checklist "langganan" category -- toggle satu baris
     * TagihanCategoryPelanggan on/off. Dinonaktifkan (status inactive),
     * BUKAN dihapus, saat pelanggan berhenti langganan (lihat docblock
     * migration create_tagihan_category_pelanggan_table.php), supaya
     * baris tagihan_penerima yang sudah pernah dibuat lewat langganan
     * ini tidak kehilangan jejak sumbernya.
     */
    public function toggleCategory(Request $request, string $id, string $categoryId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $pelanggan = $this->findOrFail($context, $id);

        $category = TagihanCategory::where('company_id', $context->company->id)
            ->where('id', $categoryId)
            ->firstOrFail();

        $existing = TagihanCategoryPelanggan::where('tagihan_category_id', $category->id)
            ->where('tagihan_pelanggan_id', $pelanggan->id)
            ->first();

        if ($existing) {
            $existing->update(['status' => $existing->status === 'active' ? 'inactive' : 'active']);
        } else {
            TagihanCategoryPelanggan::create([
                'tagihan_category_id' => $category->id,
                'tagihan_pelanggan_id' => $pelanggan->id,
                'status' => 'active',
            ]);
        }

        return redirect()
            ->route('tagihan.pelanggan.edit', $pelanggan->id)
            ->with('success', 'Langganan kategori berhasil diperbarui.');
    }

    private function branchOfficesFor(Company $company, $context)
    {
        $query = BranchOffice::where('company_id', $company->id);

        if (! $context->isOwner) {
            $query->where('id', $context->branchOffice?->id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function findOrFail($context, string $id): TagihanPelanggan
    {
        $query = TagihanPelanggan::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }

    private function validator(Request $request, Company $company, ?string $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'branch_office_id' => [
                'required', 'uuid', 'exists:branch_offices,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! BranchOffice::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Branch office tidak valid.');
                    }
                },
            ],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
