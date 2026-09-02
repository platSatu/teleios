<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalRuangan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD "Ruangan" (Jadwal v2, CLAUDE.md item #15, spec poin 2) -- murni
 * info (nama + catatan kegunaan), SENGAJA tidak mengunci ke satu Kelas/
 * Kategori tertentu (satu ruangan bisa dipakai piano lalu gitar
 * akustik gantian). Diakses lewat tombol "Ruangan" di baris Branch
 * (jadwal.branch.index), sama pola drill-down seperti
 * JadwalMataPelajaranController tapi branch_office_id WAJIB (bukan
 * opsional) karena ruangan memang milik satu lokasi fisik.
 */
class JadwalRuanganController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $request->query('branch_office_id');

        $query = JadwalRuangan::where('company_id', $company->id)
            ->with('branchOffice:id,name');

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        } elseif ($branchOfficeId) {
            $query->where('branch_office_id', $branchOfficeId);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $ruangans = $query->latest()->paginate(15)->withQueryString()->onEachSide(1);

        $branch = $branchOfficeId
            ? BranchOffice::where('company_id', $company->id)->where('id', $branchOfficeId)->first()
            : null;

        return view('jadwal.jadwal-ruangan.index', compact('ruangans', 'branch', 'branchOfficeId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('jadwal.jadwal-ruangan.create', [
            'ruangan' => null,
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
                ->route('jadwal.ruangan.create', array_filter(['branch_office_id' => $request->input('branch_office_id')]))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        JadwalRuangan::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'],
            'name' => $validated['name'],
            'catatan_kegunaan' => $validated['catatan_kegunaan'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.ruangan.index', ['branch_office_id' => $validated['branch_office_id']])
            ->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $ruangan = $this->findOrFail($context, $id);

        return view('jadwal.jadwal-ruangan.edit', [
            'ruangan' => $ruangan,
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $ruangan = $this->findOrFail($context, $id);

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.ruangan.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $ruangan->update([
            'branch_office_id' => $validated['branch_office_id'],
            'name' => $validated['name'],
            'catatan_kegunaan' => $validated['catatan_kegunaan'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.ruangan.index', ['branch_office_id' => $ruangan->branch_office_id])
            ->with('success', 'Ruangan berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $ruangan = $this->findOrFail($context, $id);
        $branchOfficeId = $ruangan->branch_office_id;

        $ruangan->delete();

        return redirect()
            ->route('jadwal.ruangan.index', ['branch_office_id' => $branchOfficeId])
            ->with('success', 'Ruangan berhasil dihapus.');
    }

    private function branchOfficesFor(Company $company, $context)
    {
        $query = BranchOffice::where('company_id', $company->id);

        if (! $context->isOwner) {
            $query->where('id', $context->branchOffice?->id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function findOrFail($context, string $id): JadwalRuangan
    {
        $query = JadwalRuangan::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }

    private function validator(Request $request, Company $company, ?string $ignoreId = null)
    {
        return Validator::make($request->all(), [
            'branch_office_id' => [
                'required', 'uuid', 'exists:branch_offices,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! BranchOffice::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Branch office tidak valid.');
                    }
                },
            ],
            'name' => [
                'required', 'string', 'max:255',
                function ($attribute, $value, $fail) use ($company, $request, $ignoreId) {
                    $exists = JadwalRuangan::where('company_id', $company->id)
                        ->where('branch_office_id', $request->input('branch_office_id'))
                        ->where('name', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Ruangan dengan nama ini sudah ada di branch tersebut.');
                    }
                },
            ],
            'catatan_kegunaan' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
