<?php

namespace App\Http\Controllers\Jadwal;

use App\Helpers\JadwalImageUploader;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalMataPelajaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for "Mata Pelajaran / Bidang" (Jadwal > Mata Pelajaran / Bidang)
 * — the subject/field catalog (musik, bahasa, dst.) App\Models\
 * JadwalKelas optionally belongs to. Branch scoping follows the same
 * rule as Chat\CategoryPhoneBookController: an owner sees/manages every
 * row, a branch-locked member only their own branch's (plus any row
 * with no branch set).
 */
class JadwalMataPelajaranController extends Controller
{
    use ResolvesCompanyContext;

    private const IMAGE_SUBDIRECTORY = 'mata-pelajaran';

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $request->query('branch_office_id');

        $query = JadwalMataPelajaran::where('company_id', $company->id)
            ->withCount('kelas')
            ->with('branchOffice:id,name');

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        } elseif ($branchOfficeId) {
            // Index ini dibuka scoped dari index Branch (tombol "+ Add
            // Mata Pelajaran / Bidang") — lihat JadwalBranchController.
            $query->where('branch_office_id', $branchOfficeId);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $mataPelajarans = $query->latest()->paginate(15)->withQueryString()->onEachSide(1);

        // Konteks Branch (kalau index ini dibuka scoped) — dipakai untuk
        // breadcrumb + tombol "Back to Branch" + mengunci branch_office_id
        // di tombol "+ Add Mata Pelajaran / Bidang".
        $branch = $branchOfficeId
            ? BranchOffice::where('company_id', $company->id)->where('id', $branchOfficeId)->first()
            : null;

        return view('jadwal.jadwal-mata-pelajaran.index', compact('mataPelajarans', 'branch', 'branchOfficeId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('jadwal.jadwal-mata-pelajaran.create', [
            'mataPelajaran' => null,
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
                ->route('jadwal.mata-pelajaran.create', array_filter(['branch_office_id' => $request->input('branch_office_id')]))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        JadwalMataPelajaran::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image' => $request->hasFile('image')
                ? JadwalImageUploader::upload($request->file('image'), self::IMAGE_SUBDIRECTORY)
                : null,
            'status' => $validated['status'] ?? 'active',
        ]);

        // Kembali ke index yang sudah di-scope ke branch itu (bukan index
        // global) kalau memang dibuat dengan konteks branch — sesuai alur
        // "ina": create -> kembali ke index yang scoped ke parent-nya.
        return redirect()
            ->route('jadwal.mata-pelajaran.index', array_filter(['branch_office_id' => $validated['branch_office_id'] ?? null]))
            ->with('success', 'Mata Pelajaran / Bidang berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $mataPelajaran = $this->findOrFail($context, $id);

        return view('jadwal.jadwal-mata-pelajaran.edit', [
            'mataPelajaran' => $mataPelajaran,
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $mataPelajaran = $this->findOrFail($context, $id);

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.mata-pelajaran.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $newImage = $mataPelajaran->image;

        if ($request->hasFile('image')) {
            $newImage = JadwalImageUploader::upload($request->file('image'), self::IMAGE_SUBDIRECTORY);
        } elseif ($request->boolean('remove_image')) {
            $newImage = null;
        }

        if ($newImage !== $mataPelajaran->image) {
            JadwalImageUploader::delete($mataPelajaran->image);
        }

        $mataPelajaran->update([
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image' => $newImage,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.mata-pelajaran.index')
            ->with('success', 'Mata Pelajaran / Bidang berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $mataPelajaran = $this->findOrFail($context, $id);

        JadwalImageUploader::delete($mataPelajaran->image);
        $mataPelajaran->delete();

        return redirect()
            ->route('jadwal.mata-pelajaran.index')
            ->with('success', 'Mata Pelajaran / Bidang berhasil dihapus.');
    }

    /**
     * Branch-locked members only ever get their own branch to pick from
     * (no picker at all, effectively forced) — same rule
     * Chat\CategoryPhoneBookController::branchOfficesFor() applies.
     */
    private function branchOfficesFor(Company $company, $context)
    {
        $query = BranchOffice::where('company_id', $company->id);

        if (! $context->isOwner) {
            $query->where('id', $context->branchOffice?->id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function findOrFail($context, string $id): JadwalMataPelajaran
    {
        $query = JadwalMataPelajaran::where('company_id', $context->company->id)
            ->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        return $query->firstOrFail();
    }

    private function validator(Request $request, Company $company, ?string $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($company, $ignoreId) {
                    $exists = JadwalMataPelajaran::where('company_id', $company->id)
                        ->where('name', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Mata Pelajaran / Bidang dengan nama ini sudah ada.');
                    }
                },
            ],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            // exists: alone only checks the row is real, not that it
            // belongs to THIS company — the closure below closes that
            // gap, same rule as Chat\CategoryPhoneBookController.
            'branch_office_id' => [
                'nullable', 'uuid', 'exists:branch_offices,id',
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
