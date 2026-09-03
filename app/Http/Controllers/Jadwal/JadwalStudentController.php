<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalMataPelajaran;
use App\Models\JadwalPengajarKategori;
use App\Models\JadwalStudent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for "Student" (Jadwal > ... > Student) — tingkat ke-4 drill-down
 * Jadwal (Branch -> Mata Pelajaran / Bidang -> Pengajar -> Student ->
 * Jadwal). Beda dari Branch/Pengajar di atasnya, Student punya tabel
 * sendiri (App\Models\JadwalStudent) jadi full CRUD, bukan cuma index.
 *
 * create() mengunci branch/mata-pelajaran/pengajar (disabled + hidden
 * input) kalau `jadwal_mata_pelajaran_id` & `pengajar_id` sama-sama ada
 * & valid di query string (selalu begitu kalau datang dari tombol
 * "+ Add Student" di index Pengajar — lihat JadwalPengajarController).
 * Tanpa konteks itu, ketiganya jadi dropdown bebas — sama pola locked-
 * vs-free seperti "ina" project's University Album Photo create/edit.
 * edit() SENGAJA TIDAK mengunci (dropdown selalu bebas), sama seperti
 * pola ina itu juga.
 */
class JadwalStudentController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $mataPelajaranId = $request->query('jadwal_mata_pelajaran_id');
        $pengajarId = $request->query('pengajar_id');
        // Murni dibawa balik-balik lewat query string untuk breadcrumb +
        // tombol "Kembali ke Pengajar" (Pengajar sekarang scoped ke
        // Kategori, bukan Mata Pelajaran langsung, lihat
        // JadwalPengajarController) -- TIDAK dipakai memfilter Student
        // (Student tetap keyed ke jadwal_mata_pelajaran_id + pengajar_id
        // seperti sebelumnya, tidak berubah).
        $kategoriId = $request->query('jadwal_kategori_id');

        $query = JadwalStudent::where('company_id', $company->id)
            ->with(['mataPelajaran:id,name', 'pengajar:id,name', 'branchOffice:id,name']);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        if ($mataPelajaranId) {
            $query->where('jadwal_mata_pelajaran_id', $mataPelajaranId);
        }

        if ($pengajarId) {
            $query->where('pengajar_id', $pengajarId);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $students = $query->orderBy('name')->paginate(15)->withQueryString()->onEachSide(1);

        // Konteks (kalau index ini dibuka scoped dari index Pengajar) —
        // dipakai untuk breadcrumb + tombol "+ Add Student" & "+ Add
        // Jadwal" yang tetap membawa konteksnya.
        $mataPelajaran = $mataPelajaranId
            ? JadwalMataPelajaran::where('company_id', $company->id)->where('id', $mataPelajaranId)->first()
            : null;
        $pengajar = $pengajarId
            ? $this->companyTeamMembers($company)->firstWhere('id', $pengajarId)
            : null;

        return view('jadwal.jadwal-student.index', compact('students', 'mataPelajaran', 'pengajar', 'mataPelajaranId', 'pengajarId', 'kategoriId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        $kategoriId = $request->query('jadwal_kategori_id');
        $pengajarId = $request->query('pengajar_id');

        // Konteks Kategori (kalau datang dari tombol "+ Add Student" di
        // index Pengajar, lihat JadwalPengajarController::index()) --
        // dipakai untuk breadcrumb "Kembali" + menampilkan panel
        // ketersediaan pengajar yang dipilih (App\Models\
        // JadwalPengajarKategori, murni info, TIDAK disimpan ke
        // jadwal_student -- tabel itu tetap cuma jadwal_mata_pelajaran_id
        // + pengajar_id seperti sebelumnya, lihat CLAUDE.md item #15).
        $pengajarAvailability = ($kategoriId && $pengajarId)
            ? JadwalPengajarKategori::with('jadwals')
                ->where('company_id', $context->company->id)
                ->where('jadwal_kategori_id', $kategoriId)
                ->where('pengajar_id', $pengajarId)
                ->first()
            : null;

        return view('jadwal.jadwal-student.create', [
            'student' => null,
            'selectedMataPelajaranId' => $request->query('jadwal_mata_pelajaran_id'),
            'selectedPengajarId' => $pengajarId,
            'selectedKategoriId' => $kategoriId,
            'pengajarAvailability' => $pengajarAvailability,
        ] + $this->formData($context));
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $kategoriId = $request->input('jadwal_kategori_id');

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.student.create', $request->only(['jadwal_mata_pelajaran_id', 'pengajar_id', 'jadwal_kategori_id']))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $mataPelajaran = JadwalMataPelajaran::where('company_id', $company->id)
            ->where('id', $validated['jadwal_mata_pelajaran_id'])
            ->first();

        JadwalStudent::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'] ?? $mataPelajaran?->branch_office_id,
            'jadwal_mata_pelajaran_id' => $validated['jadwal_mata_pelajaran_id'],
            'pengajar_id' => $validated['pengajar_id'],
            'name' => $validated['name'],
            'parent_phone_number' => $validated['parent_phone_number'] ?? null,
            'student_phone_number' => $validated['student_phone_number'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.student.index', array_filter([
                'jadwal_mata_pelajaran_id' => $validated['jadwal_mata_pelajaran_id'],
                'pengajar_id' => $validated['pengajar_id'],
                'jadwal_kategori_id' => $kategoriId,
            ]))
            ->with('success', 'Student berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $student = $this->findOrFail($context, $id);

        // TIDAK mengunci field apa pun di sini (selalu dropdown bebas) --
        // locking hanya berlaku di create(), sama seperti pola "ina"
        // project's University Album Photo edit() (lihat class docblock).
        return view('jadwal.jadwal-student.edit', [
            'student' => $student,
        ] + $this->formData($context));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $student = $this->findOrFail($context, $id);

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.student.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $mataPelajaran = JadwalMataPelajaran::where('company_id', $company->id)
            ->where('id', $validated['jadwal_mata_pelajaran_id'])
            ->first();

        $student->update([
            'branch_office_id' => $validated['branch_office_id'] ?? $mataPelajaran?->branch_office_id,
            'jadwal_mata_pelajaran_id' => $validated['jadwal_mata_pelajaran_id'],
            'pengajar_id' => $validated['pengajar_id'],
            'name' => $validated['name'],
            'parent_phone_number' => $validated['parent_phone_number'] ?? null,
            'student_phone_number' => $validated['student_phone_number'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.student.index', [
                'jadwal_mata_pelajaran_id' => $student->jadwal_mata_pelajaran_id,
                'pengajar_id' => $student->pengajar_id,
            ])
            ->with('success', 'Student berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $student = $this->findOrFail($context, $id);

        $mataPelajaranId = $student->jadwal_mata_pelajaran_id;
        $pengajarId = $student->pengajar_id;

        $student->delete();

        return redirect()
            ->route('jadwal.student.index', [
                'jadwal_mata_pelajaran_id' => $mataPelajaranId,
                'pengajar_id' => $pengajarId,
            ])
            ->with('success', 'Student berhasil dihapus.');
    }

    /**
     * Dropdown bebas untuk saat create()/edit() TIDAK datang dengan
     * konteks terkunci (lihat class docblock) — mata pelajaran, pengajar,
     * dan branch, semuanya di-scope ke company/branch yang sama seperti
     * modul Jadwal lain. `branchOffices` dipakai jadi select manual di
     * form (lihat jadwal-student/_form.blade.php) — akses langsung
     * lewat menu sidebar "Student" (bukan drill-down dari Pengajar)
     * butuh semua langkah (branch, mata pelajaran, pengajar) bisa
     * dipilih sendiri tanpa harus lewat Branch/Pengajar dulu.
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
        ];
    }

    private function findOrFail($context, string $id): JadwalStudent
    {
        $query = JadwalStudent::where('company_id', $context->company->id)
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
                'required', 'uuid', 'exists:jadwal_mata_pelajaran,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalMataPelajaran::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Mata Pelajaran / Bidang tidak valid.');
                    }
                },
            ],
            'pengajar_id' => ['required', 'uuid', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            // Format nomor tidak dipaksakan ketat di sini (angka/+/spasi
            // semua diterima) -- normalisasi ke format WA (62xxx) baru
            // dilakukan nanti di titik pengiriman (tahap pengingat WA),
            // bukan di titik input data.
            'parent_phone_number' => ['nullable', 'string', 'max:32'],
            'student_phone_number' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'in:active,inactive'],
            // exists: alone only checks the row is real, not that it
            // belongs to THIS company — sama rule seperti Jadwal\
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
