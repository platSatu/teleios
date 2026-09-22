<?php

namespace App\Http\Controllers\Tagihan;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\TagihanCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD Tagihan Category (jenis tagihan per branch, mis. "Uang Sekolah",
 * "Uang Pangkal") -- pola company/branch-scoping disamakan persis
 * dengan App\Http\Controllers\Form\FormCategoryController. Aturan denda
 * bertingkat (tagihan_denda_tier) dikelola di halaman edit category ini
 * lewat App\Http\Controllers\Tagihan\TagihanDendaTierController, bukan
 * di controller ini -- category cuma menyimpan `denda_enabled` (on/off
 * secara default untuk Tagihan baru di bawahnya).
 */
class TagihanCategoryController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $request->query('branch_office_id');

        $query = TagihanCategory::where('company_id', $company->id)
            ->withCount('tagihan')
            ->with('branchOffice:id,name');

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        } elseif ($branchOfficeId) {
            $query->where('branch_office_id', $branchOfficeId);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $categories = $query->latest()->paginate(15)->withQueryString()->onEachSide(1);

        return view('tagihan.category.index', compact('categories', 'branchOfficeId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('tagihan.category.create', [
            'category' => null,
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
                ->route('tagihan.category.create', array_filter(['branch_office_id' => $request->input('branch_office_id')]))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        TagihanCategory::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'],
            'name' => $validated['name'],
            'is_recurring' => $request->boolean('is_recurring'),
            'default_amount' => $validated['default_amount'] ?? null,
            'denda_enabled' => $request->boolean('denda_enabled'),
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('tagihan.category.index', ['branch_office_id' => $validated['branch_office_id']])
            ->with('success', 'Kategori Tagihan berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $category = $this->findOrFail($context, $id);
        $category->load('dendaTiers');

        return view('tagihan.category.edit', [
            'category' => $category,
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $category = $this->findOrFail($context, $id);

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('tagihan.category.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $category->update([
            'branch_office_id' => $validated['branch_office_id'],
            'name' => $validated['name'],
            'is_recurring' => $request->boolean('is_recurring'),
            'default_amount' => $validated['default_amount'] ?? null,
            'denda_enabled' => $request->boolean('denda_enabled'),
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('tagihan.category.index', ['branch_office_id' => $category->branch_office_id])
            ->with('success', 'Kategori Tagihan berhasil diperbarui.');
    }

    /**
     * Category yang masih punya Tagihan (periode/invoice) di bawahnya
     * TIDAK boleh dihapus -- beda dari FormCategory yang cascade-delete
     * bebas, di sini ada uang/invoice riil yang pernah/sedang ditagih,
     * jadi historinya harus tetap utuh. restrictOnDelete() di migration
     * sudah menegakkan ini di level DB juga; cek di sini semata supaya
     * pesan errornya manusiawi, bukan SQL constraint error mentah.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $category = $this->findOrFail($context, $id);
        $branchOfficeId = $category->branch_office_id;

        if ($category->tagihan()->exists()) {
            return redirect()
                ->route('tagihan.category.index', ['branch_office_id' => $branchOfficeId])
                ->with('error', 'Kategori ini masih punya Tagihan (invoice) yang pernah dibuat, tidak bisa dihapus.');
        }

        $category->delete();

        return redirect()
            ->route('tagihan.category.index', ['branch_office_id' => $branchOfficeId])
            ->with('success', 'Kategori Tagihan berhasil dihapus.');
    }

    private function branchOfficesFor(Company $company, $context)
    {
        $query = BranchOffice::where('company_id', $company->id);

        if (! $context->isOwner) {
            $query->where('id', $context->branchOffice?->id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function findOrFail($context, string $id): TagihanCategory
    {
        $query = TagihanCategory::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }

    private function validator(Request $request, Company $company, ?string $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'name' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($company, $ignoreId, $request) {
                    $exists = TagihanCategory::where('company_id', $company->id)
                        ->where('branch_office_id', $request->input('branch_office_id'))
                        ->where('name', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Kategori Tagihan dengan nama ini sudah ada di branch tersebut.');
                    }
                },
            ],
            'branch_office_id' => [
                'required', 'uuid', 'exists:branch_offices,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! BranchOffice::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Branch office tidak valid.');
                    }
                },
            ],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
