<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalKelas;
use App\Models\JadwalMataPelajaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for "Jadwal Kelas" (Jadwal > Jadwal Kelas) — one row per class
 * session: teacher, student, and its time range, optionally tied to one
 * App\Models\JadwalMataPelajaran. Branch scoping mirrors every other
 * Jadwal/Chat controller (see Jadwal\JadwalMataPelajaranController).
 *
 * create() accepts an optional `?mata_pelajaran=<id>` query param — the
 * "+ Add Class" button on the Mata Pelajaran index links here with it
 * pre-filled, so starting a class from a subject doesn't require
 * re-picking it on the next screen.
 */
class JadwalKelasController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = JadwalKelas::where('company_id', $company->id)
            ->with(['mataPelajaran:id,name', 'pengajar:id,name', 'student:id,name']);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('pengajar', fn ($qq) => $qq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('student', fn ($qq) => $qq->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('mataPelajaran', fn ($qq) => $qq->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('jadwal_mata_pelajaran_id')) {
            $query->where('jadwal_mata_pelajaran_id', $request->string('jadwal_mata_pelajaran_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $kelasList = $query->orderByRaw('start_time IS NULL, start_time DESC')
            ->paginate(15)
            ->withQueryString()
            ->onEachSide(1);

        $mataPelajarans = JadwalMataPelajaran::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('jadwal.jadwal-kelas.index', compact('kelasList', 'mataPelajarans'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('jadwal.jadwal-kelas.create', [
            'kelas' => null,
            'selectedMataPelajaranId' => $request->query('mata_pelajaran'),
        ] + $this->formData($context));
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
                ->route('jadwal.kelas.create')
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        JadwalKelas::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'jadwal_mata_pelajaran_id' => $validated['jadwal_mata_pelajaran_id'] ?? null,
            'pengajar_id' => $validated['pengajar_id'],
            'student_id' => $validated['student_id'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('jadwal.kelas.index')
            ->with('success', 'Jadwal Kelas berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $kelas = $this->findOrFail($context, $id);

        return view('jadwal.jadwal-kelas.edit', [
            'kelas' => $kelas,
            'selectedMataPelajaranId' => $kelas->jadwal_mata_pelajaran_id,
        ] + $this->formData($context));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $kelas = $this->findOrFail($context, $id);

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.kelas.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $kelas->update([
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'jadwal_mata_pelajaran_id' => $validated['jadwal_mata_pelajaran_id'] ?? null,
            'pengajar_id' => $validated['pengajar_id'],
            'student_id' => $validated['student_id'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('jadwal.kelas.index')
            ->with('success', 'Jadwal Kelas berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $kelas = $this->findOrFail($context, $id);
        $kelas->delete();

        return redirect()
            ->route('jadwal.kelas.index')
            ->with('success', 'Jadwal Kelas berhasil dihapus.');
    }

    /**
     * Shared create()/edit() form data: subject picker + the two
     * "pengajar"/"student" pickers — both sourced from
     * companyTeamMembers() (owner + active CompanyToUser members), since
     * this app has no roster separate from its own registered users
     * (per the `student_id` spec: "ambil dari table user").
     */
    private function formData($context): array
    {
        $branchOfficeId = $context->isLockedToBranch() ? $context->branchOffice?->id : null;

        return [
            'mataPelajarans' => JadwalMataPelajaran::where('company_id', $context->company->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'teamMembers' => $this->companyTeamMembers($context->company, $branchOfficeId),
        ];
    }

    private function findOrFail($context, string $id): JadwalKelas
    {
        $query = JadwalKelas::where('company_id', $context->company->id)
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
            'jadwal_mata_pelajaran_id' => [
                'nullable', 'uuid', 'exists:jadwal_mata_pelajaran,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalMataPelajaran::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Mata Pelajaran / Bidang tidak valid.');
                    }
                },
            ],
            'pengajar_id' => ['required', 'uuid', 'exists:users,id'],
            'student_id' => ['required', 'uuid', 'exists:users,id'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'status' => ['nullable', 'in:active,inactive'],
            'description' => ['nullable', 'string'],
            // exists: alone only checks the row is real, not that it
            // belongs to THIS company — same rule as Jadwal\
            // JadwalMataPelajaranController.
            'branch_office_id' => [
                'nullable', 'uuid', 'exists:branch_offices,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! BranchOffice::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Branch office tidak valid.');
                    }
                },
            ],
        ]);
    }
}
