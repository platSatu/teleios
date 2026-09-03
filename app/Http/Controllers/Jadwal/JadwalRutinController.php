<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JadwalBranchSetting;
use App\Models\JadwalKategori;
use App\Models\JadwalMataPelajaran;
use App\Models\JadwalRuangan;
use App\Models\JadwalRutin;
use App\Models\JadwalStudent;
use App\Services\Jadwal\JadwalRutinConflictService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;
use Illuminate\View\View;

/**
 * CRUD "Jadwal Rutin" (Jadwal v2, CLAUDE.md item #15, spec poin 4) --
 * "cetakan" jadwal mingguan berulang milik SATU murid: Kategori +
 * Pengajar + Ruangan (opsional) + Hari + Jam mulai + Durasi. Satu murid
 * boleh punya BANYAK baris (mis. Senin Piano Classic, Selasa Drum
 * Reguler) -- diakses lewat tombol "Jadwal Rutin" di baris index
 * Student (jadwal.student.index), jadwal_mata_pelajaran_id WAJIB ada
 * di query string (drill-down terakhir).
 *
 * Validasi bentrok pengajar/ruangan (spec poin 5) + jam operasional
 * branch (spec poin 1) dicek DI SINI, saat baris disimpan -- lihat
 * App\Services\Jadwal\JadwalRutinConflictService, dan
 * App\Models\JadwalBranchSetting::isWithinOperationalHours()/
 * isHariOperasional(). Kalau branch belum punya Jam Operasional
 * dikonfigurasi sama sekali, Jadwal Rutin TIDAK BISA dibuat -- diarahkan
 * balik dengan pesan jelas, supaya generator sesi bulanan (task
 * berikutnya) selalu punya acuan jam operasional yang valid.
 */
class JadwalRutinController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View|RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $studentId = $request->query('student_id');
        $student = $studentId ? $this->ownedStudentOrFail($context, $studentId) : null;

        if (! $student) {
            return redirect()
                ->route('jadwal.student.index')
                ->with('error', 'Pilih Student terlebih dahulu sebelum mengelola Jadwal Rutin.');
        }

        $rutins = JadwalRutin::where('company_id', $company->id)
            ->where('student_id', $student->id)
            ->with(['kategori.mataPelajaran:id,name', 'pengajar:id,name', 'ruangan:id,name'])
            ->orderBy('hari')
            ->orderBy('jam_mulai')
            ->get();

        return view('jadwal.jadwal-rutin.index', compact('rutins', 'student', 'studentId'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $context = $this->companyContext($request);

        $student = $this->ownedStudentOrFail($context, $request->query('student_id'));

        if (! $student) {
            return redirect()
                ->route('jadwal.student.index')
                ->with('error', 'Pilih Student terlebih dahulu sebelum menambah Jadwal Rutin.');
        }

        return view('jadwal.jadwal-rutin.create', [
            'rutin' => null,
            'student' => $student,
        ] + $this->formData($context, $student));
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $student = $this->ownedStudentOrFail($context, $request->input('student_id'));

        if (! $student) {
            return redirect()->route('jadwal.student.index')->with('error', 'Student tidak valid.');
        }

        $branchSetting = $this->branchSettingFor($student);

        $validator = $this->validator($request, $company, $student, $branchSetting);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.rutin.create', ['student_id' => $student->id])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        JadwalRutin::create([
            'company_id' => $company->id,
            'branch_office_id' => $student->branch_office_id,
            'student_id' => $student->id,
            'jadwal_kategori_id' => $validated['jadwal_kategori_id'],
            'pengajar_id' => $validated['pengajar_id'],
            'jadwal_ruangan_id' => $validated['jadwal_ruangan_id'] ?? null,
            'hari' => $validated['hari'],
            'jam_mulai' => $validated['jam_mulai'],
            'durasi_menit' => $validated['durasi_menit'] ?? null,
            'efektif_mulai' => $validated['efektif_mulai'],
            'efektif_selesai' => $validated['efektif_selesai'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.rutin.index', ['student_id' => $student->id])
            ->with('success', 'Jadwal Rutin berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $rutin = $this->findOrFail($context, $id);
        $student = $rutin->student;

        return view('jadwal.jadwal-rutin.edit', [
            'rutin' => $rutin,
            'student' => $student,
        ] + $this->formData($context, $student));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $rutin = $this->findOrFail($context, $id);
        $student = $rutin->student;

        $branchSetting = $this->branchSettingFor($student);

        $validator = $this->validator($request, $company, $student, $branchSetting, $rutin->id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.rutin.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $rutin->update([
            'jadwal_kategori_id' => $validated['jadwal_kategori_id'],
            'pengajar_id' => $validated['pengajar_id'],
            'jadwal_ruangan_id' => $validated['jadwal_ruangan_id'] ?? null,
            'hari' => $validated['hari'],
            'jam_mulai' => $validated['jam_mulai'],
            'durasi_menit' => $validated['durasi_menit'] ?? null,
            'efektif_mulai' => $validated['efektif_mulai'],
            'efektif_selesai' => $validated['efektif_selesai'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.rutin.index', ['student_id' => $student->id])
            ->with('success', 'Jadwal Rutin berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $rutin = $this->findOrFail($context, $id);
        $studentId = $rutin->student_id;

        $rutin->delete();

        return redirect()
            ->route('jadwal.rutin.index', ['student_id' => $studentId])
            ->with('success', 'Jadwal Rutin berhasil dihapus.');
    }

    private function ownedStudentOrFail($context, ?string $id): ?JadwalStudent
    {
        if (! $id) {
            return null;
        }

        $query = JadwalStudent::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        return $query->first();
    }

    private function findOrFail($context, string $id): JadwalRutin
    {
        return JadwalRutin::where('company_id', $context->company->id)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function branchSettingFor(JadwalStudent $student): ?JadwalBranchSetting
    {
        if (! $student->branch_office_id) {
            return null;
        }

        return JadwalBranchSetting::where('branch_office_id', $student->branch_office_id)->first();
    }

    /**
     * Dropdown untuk create()/edit(): Kategori dikelompokkan per Kelas
     * (mata pelajaran), dari SELURUH Kelas company (branch-scoped) --
     * BUKAN cuma Kelas yang tersimpan di jadwal_student.jadwal_mata_pelajaran_id,
     * karena satu murid boleh punya Jadwal Rutin lintas Kelas berbeda
     * (spec poin 4). Pengajar & Ruangan juga branch-scoped yang sama.
     */
    private function formData($context, JadwalStudent $student): array
    {
        $branchOfficeId = $student->branch_office_id ?? ($context->isLockedToBranch() ? $context->branchOffice?->id : null);

        $mataPelajarans = JadwalMataPelajaran::where('company_id', $context->company->id)
            ->where('status', 'active')
            ->when($branchOfficeId, fn ($q) => $q->where(function ($q2) use ($branchOfficeId) {
                $q2->where('branch_office_id', $branchOfficeId)->orWhereNull('branch_office_id');
            }))
            ->with(['kategoris' => fn ($q) => $q->where('status', 'active')->orderBy('name')])
            ->orderBy('name')
            ->get();

        return [
            'mataPelajarans' => $mataPelajarans,
            'teamMembers' => $this->companyPengajarMembers($context->company, $branchOfficeId),
            'ruangans' => JadwalRuangan::where('company_id', $context->company->id)
                ->where('status', 'active')
                ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
                ->orderBy('name')
                ->get(['id', 'name']),
            'branchSetting' => $this->branchSettingFor($student),
        ];
    }

    private function validator(
        Request $request,
        Company $company,
        JadwalStudent $student,
        ?JadwalBranchSetting $branchSetting,
        ?string $ignoreId = null,
    ): ValidatorContract {
        $validator = Validator::make($request->all(), [
            'jadwal_kategori_id' => [
                'required', 'uuid', 'exists:jadwal_kategori,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalKategori::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Kategori tidak valid.');
                    }
                },
            ],
            'pengajar_id' => ['required', 'uuid', 'exists:users,id'],
            'jadwal_ruangan_id' => [
                'nullable', 'uuid', 'exists:jadwal_ruangan,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalRuangan::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Ruangan tidak valid.');
                    }
                },
            ],
            'hari' => ['required', 'integer', 'between:0,6'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'durasi_menit' => ['nullable', 'integer', 'min:5', 'max:600'],
            'efektif_mulai' => ['required', 'date'],
            'efektif_selesai' => ['nullable', 'date', 'after_or_equal:efektif_mulai'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $validator->after(function (ValidatorContract $v) use ($request, $company, $student, $branchSetting, $ignoreId) {
            if (! $branchSetting) {
                $v->errors()->add('hari', 'Branch "'.($student->branchOffice?->name ?? '-').'" belum punya Jam Operasional -- atur dulu lewat menu Jadwal > Branch > Jam Operasional sebelum membuat Jadwal Rutin.');

                return;
            }

            $hari = (int) $request->input('hari');
            $jamMulai = (string) $request->input('jam_mulai');
            $durasi = $request->filled('durasi_menit')
                ? (int) $request->input('durasi_menit')
                : $branchSetting->durasi_sesi_default_menit;

            if ($hari === null || $jamMulai === '') {
                return;
            }

            $jamSelesai = Carbon::createFromFormat('H:i', $jamMulai)->addMinutes($durasi)->format('H:i');

            if (! $branchSetting->isHariOperasional($hari)) {
                $v->errors()->add('hari', 'Branch tidak buka di hari '.(\App\Models\JadwalRutin::HARI_LABELS[$hari] ?? $hari).' (lihat Jam Operasional).');
            } elseif (! $branchSetting->isWithinOperationalHours($jamMulai, $jamSelesai)) {
                $v->errors()->add('jam_mulai', 'Jam '.$jamMulai.'-'.$jamSelesai.' di luar jam operasional branch ('.substr($branchSetting->jam_buka, 0, 5).'-'.substr($branchSetting->jam_tutup, 0, 5).'), atau bentrok jam istirahat.');
            }

            if ($v->errors()->isEmpty()) {
                $conflicts = app(JadwalRutinConflictService::class)->check(
                    companyId: $company->id,
                    hari: $hari,
                    jamMulai: $jamMulai,
                    jamSelesai: $jamSelesai,
                    efektifMulai: (string) $request->input('efektif_mulai'),
                    efektifSelesai: $request->input('efektif_selesai') ?: null,
                    pengajarId: (string) $request->input('pengajar_id'),
                    jadwalRuanganId: $request->input('jadwal_ruangan_id') ?: null,
                    ignoreId: $ignoreId,
                );

                foreach ($conflicts as $message) {
                    $v->errors()->add('hari', $message);
                }
            }
        });

        return $validator;
    }
}
