<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalKelas;
use App\Models\JadwalMataPelajaran;
use App\Models\JadwalStudent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for "Jadwal Kelas" (Jadwal > Jadwal Kelas) — satu baris per sesi
 * kelas: pengajar, murid (App\Models\JadwalStudent), dan rentang
 * waktunya, opsional terhubung ke satu App\Models\JadwalMataPelajaran.
 * Tingkat terakhir/terdalam drill-down Jadwal (Branch -> Mata Pelajaran
 * / Bidang -> Pengajar -> Student -> Jadwal).
 *
 * create() mengunci SEMUA 4 field (branch, mata pelajaran, pengajar,
 * student) kalau semuanya ada & valid di query string — selalu begitu
 * kalau datang dari tombol "+ Add Jadwal" di index Student (lihat
 * JadwalStudentController). Tanpa konteks lengkap itu, tetap bisa
 * dibuat "bebas" lewat dropdown biasa (mis. dari menu top-level "Jadwal
 * Kelas" langsung) — sama pola locked-vs-free seperti level Jadwal
 * lainnya, mengikuti "ina" project.
 */
class JadwalKelasController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $studentId = $request->query('student_id');

        $query = JadwalKelas::where('company_id', $company->id)
            ->with(['mataPelajaran:id,name', 'pengajar:id,name', 'student:id,name']);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        if ($studentId) {
            $query->where('student_id', $studentId);
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

        // Konteks Student (kalau index ini dibuka scoped dari index
        // Student) — dipakai untuk breadcrumb + tombol "Back" & "+ Add
        // Jadwal" yang tetap membawa konteksnya.
        $student = $studentId
            ? JadwalStudent::where('company_id', $company->id)->where('id', $studentId)->first()
            : null;

        return view('jadwal.jadwal-kelas.index', compact('kelasList', 'mataPelajarans', 'student', 'studentId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('jadwal.jadwal-kelas.create', [
            'kelas' => null,
            'selectedBranchOfficeId' => $request->query('branch_office_id'),
            'selectedMataPelajaranId' => $request->query('jadwal_mata_pelajaran_id'),
            'selectedPengajarId' => $request->query('pengajar_id'),
            'selectedStudentId' => $request->query('student_id'),
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
                ->route('jadwal.kelas.create', $request->only([
                    'branch_office_id', 'jadwal_mata_pelajaran_id', 'pengajar_id', 'student_id',
                ]))
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

        // Kembali ke index yang sudah di-scope ke student itu (bukan
        // index global) — sesuai alur "ina": create -> kembali ke index
        // yang scoped ke parent-nya.
        return redirect()
            ->route('jadwal.kelas.index', ['student_id' => $validated['student_id']])
            ->with('success', 'Jadwal Kelas berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $kelas = $this->findOrFail($context, $id);

        // TIDAK mengunci field apa pun di sini (selalu dropdown bebas) --
        // locking hanya berlaku di create(), sama seperti pola "ina"
        // project's University Album Photo edit() (lihat class docblock).
        return view('jadwal.jadwal-kelas.edit', [
            'kelas' => $kelas,
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
            ->route('jadwal.kelas.index', ['student_id' => $kelas->student_id])
            ->with('success', 'Jadwal Kelas berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $kelas = $this->findOrFail($context, $id);
        $studentId = $kelas->student_id;
        $kelas->delete();

        return redirect()
            ->route('jadwal.kelas.index', ['student_id' => $studentId])
            ->with('success', 'Jadwal Kelas berhasil dihapus.');
    }

    /**
     * Shared create()/edit() form data untuk mode BEBAS (tanpa konteks
     * terkunci — lihat class docblock): mata pelajaran + pengajar
     * (companyTeamMembers, sama seperti sebelumnya) + student (sekarang
     * dari App\Models\JadwalStudent, BUKAN lagi companyTeamMembers —
     * lihat migration create_jadwal_kelas_table.php's docblock untuk
     * kenapa `student_id` pindah FK).
     */
    private function formData($context): array
    {
        $branchOfficeId = $context->isLockedToBranch() ? $context->branchOffice?->id : null;

        return [
            'branchOffices' => BranchOffice::where('company_id', $context->company->id)
                ->when($branchOfficeId, fn ($q) => $q->where('id', $branchOfficeId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'mataPelajarans' => JadwalMataPelajaran::where('company_id', $context->company->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'teamMembers' => $this->companyTeamMembers($context->company, $branchOfficeId),
            // Label deskriptif ("Ana — Piano (diajar Sarah)") supaya mode
            // bebas tetap bisa langsung pilih student tanpa perlu
            // cascading select (pilih mata pelajaran -> pengajar dulu).
            'students' => JadwalStudent::where('company_id', $context->company->id)
                ->where('status', 'active')
                ->with(['mataPelajaran:id,name', 'pengajar:id,name'])
                ->orderBy('name')
                ->get(),
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
            'student_id' => [
                'required', 'uuid', 'exists:jadwal_student,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalStudent::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Student tidak valid.');
                    }
                },
            ],
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
