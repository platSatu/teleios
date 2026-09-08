<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JadwalGrade;
use App\Models\JadwalPengajarGrade;
use App\Services\Jadwal\JadwalCountsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;
use Illuminate\View\View;

/**
 * CRUD "Pengajar" (restrukturisasi drill-down Jadwal 14 September 2026,
 * atas permintaan user; RESCOPED ke Grade 8 September 2026, permintaan
 * user berikutnya) — level di antara Grade dan Student: Branch ->
 * Ruangan -> Jam Operasional -> Mata Pelajaran / Bidang -> Kategori ->
 * Grade -> **Pengajar** -> Student.
 *
 * Update 8 September 2026 (fitur Grade): penugasan Pengajar + jam
 * ketersediaan yang SEBELUMNYA menempel langsung ke Kategori sekarang
 * menempel ke Grade (App\Models\JadwalGrade, level baru di bawah
 * Kategori) -- lihat docblock App\Models\JadwalGrade & App\Models\
 * JadwalPengajarGrade untuk alasan lengkap. Controller ini SEKARANG
 * baca/tulis App\Models\JadwalPengajarGrade (BUKAN lagi
 * App\Models\JadwalPengajarKategori, yang dibiarkan sebagai data
 * historis/frozen) -- pola & struktur di bawah PERSIS sama dengan
 * sebelumnya, cuma "Kategori" diganti "Grade" di seluruh alur.
 *
 * Update 3 September 2026 (masih permintaan user, sesi yang sama):
 * Pengajar SEKARANG PUNYA MENU SENDIRI di sidebar (lihat
 * resources/views/layouts/partials/menu.blade.php) — TIDAK cuma
 * dijangkau lewat drill-down "+ Add Pengajar" di index Kategori/Grade.
 * Polanya sama seperti App\Http\Controllers\Jadwal\
 * JadwalMataPelajaranController / JadwalStudentController: index()
 * mode GLOBAL (tanpa `jadwal_grade_id`) menampilkan SEMUA baris
 * company (+ kolom Grade), mode SCOPED (dengan `jadwal_grade_id`,
 * datang dari tombol drill-down) memfilter ke satu Grade & sembunyikan
 * kolom yang jadi redundant. create()/edit() ikut pola locked-vs-free
 * "ina" project's University Album Photo: Grade terkunci (disabled +
 * hidden input) kalau datang dengan `jadwal_grade_id` valid di query
 * string, dropdown bebas kalau tidak (termasuk SELALU bebas di edit()).
 *
 * Pengajar (App\Models\JadwalPengajarGrade) = penugasan Pengajar
 * (tetap user perusahaan yang sudah ada, lewat ResolvesCompanyContext::
 * companyTeamMembers()) ke satu Grade, dengan hari & jam
 * ketersediaannya sendiri untuk Grade itu. Validasi (mis. pengajar
 * sudah terdaftar di Grade yang sama) ditampilkan lewat alert error
 * standar di atas form (`$errors->any()`, sama seperti form lain di
 * seluruh app ini) kalau gagal disimpan.
 *
 * Ketersediaan di sini MURNI INFO ditampilkan di form Add Student
 * (lihat JadwalStudentController::create()) — TIDAK divalidasi silang
 * ke App\Models\JadwalRutin, validasi bentrok jadwal tetap sepenuhnya
 * di App\Services\Jadwal\JadwalRutinConflictService seperti sebelumnya.
 */
class JadwalPengajarController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(
        protected JadwalCountsService $countsService,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $gradeId = $request->query('jadwal_grade_id');
        $grade = $gradeId ? $this->ownedGradeOrFail($context, $gradeId) : null;

        if ($gradeId && ! $grade) {
            return redirect()
                ->route('jadwal.pengajar.index')
                ->with('error', 'Grade tidak ditemukan.');
        }

        $query = JadwalPengajarGrade::where('company_id', $company->id)
            ->with(['pengajar:id,name,email', 'grade.kategori.mataPelajaran:id,name,branch_office_id', 'jadwals']);

        if ($grade) {
            $query->where('jadwal_grade_id', $grade->id);
        } elseif ($context->isLockedToBranch()) {
            // Mode global (tanpa Grade) tapi anggota branch-locked --
            // tetap batasi ke Grade yang Mata Pelajaran-nya milik
            // branch dia (atau lintas-branch, sama rule seperti
            // JadwalGradeController::ownedKategoriOrFail()).
            $branchOfficeId = $context->branchOffice?->id;
            $query->whereHas('grade.kategori.mataPelajaran', function ($q) use ($branchOfficeId) {
                $q->where('branch_office_id', $branchOfficeId)->orWhereNull('branch_office_id');
            });
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('pengajar', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));
        }

        $pengajarGrades = $query->orderBy('created_at')->paginate(15)->withQueryString()->onEachSide(1);

        $this->attachMuridCounts($pengajarGrades, $company);

        return view('jadwal.jadwal-pengajar.index', [
            'pengajarGrades' => $pengajarGrades,
            'grade' => $grade,
            'kategori' => $grade?->kategori,
            'mataPelajaran' => $grade?->kategori?->mataPelajaran,
        ]);
    }

    /**
     * Jumlah MURID per baris Pengajar -- diklik pindah ke
     * jadwal.student.index (badge, sama pola link "Add Student" di
     * index.blade.php). Satu query dikelompokkan (bukan query per baris
     * di dalam loop) supaya tidak N+1 walau paginate menampilkan 15
     * baris sekaligus.
     *
     * Update 8 September 2026 (fitur Grade) -- pasangan sekarang
     * (pengajar_id, jadwal_grade_id), diambil dari kolom
     * jadwal_grade_id milik baris JadwalPengajarGrade ini sendiri,
     * lewat App\Services\Jadwal\JadwalCountsService::
     * activeMuridCountsForGrades() (versi Grade dari
     * activeMuridCountsForKategoris() lama).
     */
    private function attachMuridCounts(LengthAwarePaginator $pengajarGrades, Company $company): void
    {
        $pairs = collect($pengajarGrades->items())
            ->map(fn (JadwalPengajarGrade $pg) => [
                'pengajar_id' => $pg->pengajar_id,
                'jadwal_grade_id' => $pg->jadwal_grade_id,
            ])
            ->unique(fn (array $p) => $p['pengajar_id'].'|'.$p['jadwal_grade_id']);

        $counts = $this->countsService->activeMuridCountsForGrades($company->id, $pairs);

        foreach ($pengajarGrades as $pg) {
            $pg->murid_count = $counts->get($pg->pengajar_id.'|'.$pg->jadwal_grade_id, 0);
        }
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        $gradeId = $request->query('jadwal_grade_id');
        $grade = $gradeId ? $this->ownedGradeOrFail($context, $gradeId) : null;

        return view('jadwal.jadwal-pengajar.create', [
            'pengajarGrade' => null,
            'grade' => $grade,
            'selectedGradeId' => $gradeId,
            'kategori' => $grade?->kategori,
            'mataPelajaran' => $grade?->kategori?->mataPelajaran,
        ] + $this->formData($context, $grade));
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $validator = $this->validator($request, $context);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.pengajar.create', array_filter(['jadwal_grade_id' => $request->input('jadwal_grade_id')]))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Divalidasi ulang di sini (bukan cuma exists+company di
        // validator()) supaya branch-lock ke Mata Pelajaran-nya
        // Kategori-nya Grade ikut ditegakkan, sama pola seperti
        // findOrFail() di controller Jadwal lain.
        $grade = $this->ownedGradeOrFail($context, $validated['jadwal_grade_id']);
        abort_if(! $grade, 404);

        DB::transaction(function () use ($company, $grade, $validated) {
            $pengajarGrade = JadwalPengajarGrade::create([
                'company_id' => $company->id,
                'jadwal_grade_id' => $grade->id,
                'pengajar_id' => $validated['pengajar_id'],
                'status' => $validated['status'] ?? 'active',
            ]);

            $this->syncJadwal($pengajarGrade, $validated['jadwal']);
        });

        return redirect()
            ->route('jadwal.pengajar.index', ['jadwal_grade_id' => $grade->id])
            ->with('success', 'Pengajar berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $pengajarGrade = $this->findOrFail($context, $id);
        $grade = $pengajarGrade->grade;

        // SENGAJA TIDAK mengunci Grade di sini (selalu dropdown bebas)
        // -- locking cuma berlaku di create(), sama pola "ina" project's
        // University Album Photo edit() (lihat class docblock).
        return view('jadwal.jadwal-pengajar.edit', [
            'pengajarGrade' => $pengajarGrade,
            'grade' => null,
            'kategori' => $grade->kategori,
            'mataPelajaran' => $grade->kategori?->mataPelajaran,
        ] + $this->formData($context, null));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $pengajarGrade = $this->findOrFail($context, $id);

        $validator = $this->validator($request, $context, $pengajarGrade->id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.pengajar.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $grade = $this->ownedGradeOrFail($context, $validated['jadwal_grade_id']);
        abort_if(! $grade, 404);

        DB::transaction(function () use ($pengajarGrade, $grade, $validated) {
            $pengajarGrade->update([
                'jadwal_grade_id' => $grade->id,
                'pengajar_id' => $validated['pengajar_id'],
                'status' => $validated['status'] ?? 'active',
            ]);

            $this->syncJadwal($pengajarGrade, $validated['jadwal']);
        });

        return redirect()
            ->route('jadwal.pengajar.index', ['jadwal_grade_id' => $grade->id])
            ->with('success', 'Pengajar berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $pengajarGrade = $this->findOrFail($context, $id);
        $gradeId = $pengajarGrade->jadwal_grade_id;

        // AMAN dihapus -- Jadwal Rutin/sesi yang sudah dibuat pengajar
        // ini tidak ikut terhapus (referensi ke users.id langsung, tidak
        // FK ke baris ini, lihat docblock App\Models\
        // JadwalPengajarGrade).
        $pengajarGrade->delete();

        return redirect()
            ->route('jadwal.pengajar.index', ['jadwal_grade_id' => $gradeId])
            ->with('success', 'Pengajar berhasil dihapus dari Grade ini.');
    }

    /**
     * @param  JadwalGrade|null  $grade  Kalau null, form menampilkan
     *      dropdown Grade bebas (lihat class docblock) -- daftar
     *      `grades` di bawah cuma dihitung kalau memang dibutuhkan.
     */
    private function formData($context, ?JadwalGrade $grade): array
    {
        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $grade?->kategori?->mataPelajaran?->branch_office_id;

        return [
            'teamMembers' => $this->companyTeamMembers($context->company, $branchOfficeId),
            'grades' => $grade ? collect() : JadwalGrade::with('kategori.mataPelajaran:id,name,branch_office_id')
                ->where('company_id', $context->company->id)
                ->where('status', 'active')
                ->when($context->isLockedToBranch(), function ($q) use ($context) {
                    $branchOfficeId = $context->branchOffice?->id;
                    $q->whereHas('kategori.mataPelajaran', function ($qq) use ($branchOfficeId) {
                        $qq->where('branch_office_id', $branchOfficeId)->orWhereNull('branch_office_id');
                    });
                })
                ->orderBy('name')
                ->get(),
        ];
    }

    private function ownedGradeOrFail($context, ?string $id): ?JadwalGrade
    {
        if (! $id) {
            return null;
        }

        $grade = JadwalGrade::with('kategori.mataPelajaran')
            ->where('company_id', $context->company->id)
            ->where('id', $id)
            ->first();

        if ($grade && $context->isLockedToBranch()) {
            $branchOfficeId = $grade->kategori?->mataPelajaran?->branch_office_id;

            if ($branchOfficeId && $branchOfficeId !== $context->branchOffice?->id) {
                return null;
            }
        }

        return $grade;
    }

    private function findOrFail($context, string $id): JadwalPengajarGrade
    {
        return JadwalPengajarGrade::with(['grade.kategori.mataPelajaran', 'jadwals'])
            ->where('company_id', $context->company->id)
            ->where('id', $id)
            ->firstOrFail();
    }

    /**
     * Ganti seluruh slot jadwal (App\Models\JadwalPengajarGradeJadwal)
     * milik satu penugasan Pengajar dengan `$rows` -- hapus semua baris
     * lama, buat ulang dari input yang baru. Lebih sederhana & aman
     * daripada diff per-baris (jumlah baris bisa berubah bebas:
     * tambah/hapus dari form "Tambah Baris" di UI), dan aman dipanggil
     * untuk penugasan yang baru dibuat (jadwals() kosong, delete()
     * no-op).
     *
     * @param  array<int, array{hari: int|string, jam_mulai: string, jam_selesai: string}>  $rows
     */
    private function syncJadwal(JadwalPengajarGrade $pengajarGrade, array $rows): void
    {
        $pengajarGrade->jadwals()->delete();

        foreach ($rows as $row) {
            $pengajarGrade->jadwals()->create([
                'hari' => (int) $row['hari'],
                'jam_mulai' => $row['jam_mulai'],
                'jam_selesai' => $row['jam_selesai'],
            ]);
        }
    }

    private function validator(Request $request, $context, ?string $ignoreId = null): ValidatorContract
    {
        $company = $context->company;

        $validator = Validator::make($request->all(), [
            'jadwal_grade_id' => [
                'required', 'uuid', 'exists:jadwal_grade,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalGrade::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Grade tidak valid.');
                    }
                },
            ],
            'pengajar_id' => [
                'required', 'uuid', 'exists:users,id',
                function ($attribute, $value, $fail) use ($company, $request, $ignoreId) {
                    $gradeId = $request->input('jadwal_grade_id');

                    $exists = JadwalPengajarGrade::where('company_id', $company->id)
                        ->where('jadwal_grade_id', $gradeId)
                        ->where('pengajar_id', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Pengajar ini sudah terdaftar di Grade ini.');
                    }
                },
            ],
            // Ketersediaan sekarang berupa BANYAK slot (hari + jam),
            // bukan satu hari_bisa[] + satu jam_mulai/jam_selesai yang
            // berlaku sama ke semua hari -- lihat App\Models\
            // JadwalPengajarGradeJadwal & class docblock. Satu hari
            // BOLEH muncul lebih dari sekali (mis. Senin 10-12 dan
            // Senin 17-19), jadi TIDAK ada rule unique di sini, cuma
            // dicek jam_selesai > jam_mulai per baris lewat
            // $validator->after() di bawah.
            'jadwal' => ['required', 'array', 'min:1'],
            'jadwal.*.hari' => ['required', 'integer', 'between:0,6'],
            'jadwal.*.jam_mulai' => ['required', 'date_format:H:i'],
            'jadwal.*.jam_selesai' => ['required', 'date_format:H:i'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $validator->after(function ($v) use ($request) {
            foreach ((array) $request->input('jadwal', []) as $i => $row) {
                $mulai = $row['jam_mulai'] ?? null;
                $selesai = $row['jam_selesai'] ?? null;

                if ($mulai && $selesai && $mulai >= $selesai) {
                    $v->errors()->add("jadwal.{$i}.jam_selesai", 'Jam selesai harus setelah jam mulai.');
                }
            }
        });

        return $validator;
    }
}
