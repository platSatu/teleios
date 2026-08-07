<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\MataPelajaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for a cabang's own Mata Pelajaran catalog — always
 * branch_office_id NOT NULL (unlike Chat's WaCategoryPhoneBook, which
 * allows a company-wide null branch), per the spec: "1 cabang punya
 * mata pelajaran yang tidak sama dengan cabang lainnya meskipun di
 * dalam satu perusahaan". Same owner-sees-everything /
 * branch-locked-member-sees-their-branch scoping convention as
 * Chat\CategoryPhoneBookController.
 */
class MataPelajaranController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = MataPelajaran::where('company_id', $company->id)
            ->withCount('jadwalKelas')
            ->with('branchOffice:id,name');

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        if ($request->filled('branch_office_id') && $context->isOwner) {
            $query->where('branch_office_id', $request->string('branch_office_id'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $mataPelajaran = $query->latest()->paginate(10)->withQueryString();

        return view('jadwal.mata-pelajaran.index', [
            'mataPelajaran' => $mataPelajaran,
            'branchOffices' => $this->branchOfficesFor($company, $context),
        ]);
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('jadwal.mata-pelajaran.create', [
            'item' => null,
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
                ->route('jadwal.mata-pelajaran.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        MataPelajaran::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'durasi_menit' => $validated['durasi_menit'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $item = $this->findOrFail($context, $id);

        return view('jadwal.mata-pelajaran.edit', [
            'item' => $item,
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $item = $this->findOrFail($context, $id);

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

        $item->update([
            'branch_office_id' => $validated['branch_office_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'durasi_menit' => $validated['durasi_menit'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $item = $this->findOrFail($context, $id);

        if ($item->jadwalKelas()->exists()) {
            return redirect()
                ->route('jadwal.mata-pelajaran.index')
                ->with('error', 'Mata pelajaran ini masih dipakai oleh jadwal kelas — hapus/pindahkan jadwal kelasnya dulu.');
        }

        $item->delete();

        return redirect()
            ->route('jadwal.mata-pelajaran.index')
            ->with('success', 'Mata pelajaran berhasil dihapus.');
    }

    private function branchOfficesFor(Company $company, $context)
    {
        $query = BranchOffice::where('company_id', $company->id);

        if (! $context->isOwner) {
            $query->where('id', $context->branchOffice?->id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function findOrFail($context, string $id): MataPelajaran
    {
        $query = MataPelajaran::where('company_id', $context->company->id)->where('id', $id);

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
                        $fail('Cabang tidak valid.');
                    }
                },
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($request, $ignoreId) {
                    $branchOfficeId = $request->input('branch_office_id');

                    $exists = MataPelajaran::where('branch_office_id', $branchOfficeId)
                        ->where('name', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Mata pelajaran dengan nama ini sudah ada di cabang tersebut.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'durasi_menit' => ['nullable', 'integer', 'min:5', 'max:600'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
