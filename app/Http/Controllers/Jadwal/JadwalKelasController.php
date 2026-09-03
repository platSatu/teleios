<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalKelas;
use App\Models\JadwalMataPelajaran;
use App\Models\JadwalReminderSetting;
use App\Models\JadwalStudent;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\PackageLimitService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

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

    public function __construct(
        protected PackageLimitService $packageLimits,
        protected SystemJwtService $jwtService,
        protected InboxService $inbox,
    ) {
    }

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $studentId = $request->query('student_id');

        $query = JadwalKelas::where('company_id', $company->id)
            ->with([
                'mataPelajaran:id,name', 'pengajar:id,name', 'student:id,name',
                'sesiPengganti:id,pengganti_dari_sesi_id,start_time',
                'penggantiDariSesi:id,start_time',
            ]);

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

        // Filter tanggal: preset ('today'/'this_week'/'this_month') selalu
        // menang atas rentang custom (date_from/date_to) kalau dua-duanya
        // ke-isi -- lebih predictable daripada digabung. Tanpa preset,
        // date_from/date_to dipakai independen (boleh isi salah satu saja,
        // mis. cuma "dari tanggal" tanpa batas atas).
        $dateFilter = $request->query('date_filter');

        if ($dateFilter === 'today') {
            $query->whereDate('start_time', now()->toDateString());
        } elseif ($dateFilter === 'this_week') {
            $query->whereBetween('start_time', [now()->startOfWeek(), now()->endOfWeek()]);
        } elseif ($dateFilter === 'this_month') {
            $query->whereBetween('start_time', [now()->startOfMonth(), now()->endOfMonth()]);
        } else {
            if ($request->filled('date_from')) {
                $query->whereDate('start_time', '>=', $request->string('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('start_time', '<=', $request->string('date_to'));
            }
        }

        // Diurutkan per pengajar+mata pelajaran dulu (bukan per waktu)
        // supaya baris-baris sejenis bersebelahan -- index-nya
        // menggabungkan sel Pengajar & Mata Pelajaran / Bidang ala Excel
        // untuk baris-baris berurutan yang pengajar+mata-pelajaran-nya
        // sama (lihat jadwal-kelas/index.blade.php), meniru tampilan
        // rekap kehadiran per kelas walau datanya tetap 1 baris per
        // student.
        $kelasList = $query
            ->orderBy('pengajar_id')
            ->orderByRaw('jadwal_mata_pelajaran_id IS NULL, jadwal_mata_pelajaran_id')
            ->orderByRaw('start_time IS NULL, start_time DESC')
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

        $penggantiDariSesi = $request->query('pengganti_dari_sesi_id')
            ? $this->findOrFail($context, $request->query('pengganti_dari_sesi_id'))
            : null;

        return view('jadwal.jadwal-kelas.create', [
            'kelas' => null,
            'selectedBranchOfficeId' => $request->query('branch_office_id') ?? $penggantiDariSesi?->branch_office_id,
            'selectedMataPelajaranId' => $request->query('jadwal_mata_pelajaran_id') ?? $penggantiDariSesi?->jadwal_mata_pelajaran_id,
            'selectedPengajarId' => $request->query('pengajar_id') ?? $penggantiDariSesi?->pengajar_id,
            'selectedStudentId' => $request->query('student_id') ?? $penggantiDariSesi?->student_id,
            'penggantiDariSesi' => $penggantiDariSesi,
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

        // Sesi PENGGANTI (izin/sakit, spec Jadwal v2 poin 8) -- baris
        // BARU yang menunjuk ke sesi asli, mewarisi snapshot
        // kategori/ruangan/durasi/harga/persentase dari sesi asli itu
        // (form manual di sini tidak punya field-field itu sendiri;
        // sesi pengganti secara definisi menggantikan hak yang sama
        // dengan sesi asli, bukan transaksi baru).
        $penggantiDariSesi = null;
        $snapshot = [];

        if (! empty($validated['pengganti_dari_sesi_id'])) {
            $penggantiDariSesi = $this->findOrFail($context, $validated['pengganti_dari_sesi_id']);
            $snapshot = [
                'jadwal_rutin_id' => $penggantiDariSesi->jadwal_rutin_id,
                'jadwal_kategori_id' => $penggantiDariSesi->jadwal_kategori_id,
                'jadwal_ruangan_id' => $penggantiDariSesi->jadwal_ruangan_id,
                'duration_minutes' => $penggantiDariSesi->duration_minutes,
                'harga_sesi' => $penggantiDariSesi->harga_sesi,
                'persentase_company' => $penggantiDariSesi->persentase_company,
                'persentase_pengajar' => $penggantiDariSesi->persentase_pengajar,
            ];
        }

        try {
            JadwalKelas::create(array_merge([
                'company_id' => $company->id,
                'branch_office_id' => $validated['branch_office_id'] ?? null,
                'jadwal_mata_pelajaran_id' => $validated['jadwal_mata_pelajaran_id'] ?? null,
                'pengajar_id' => $validated['pengajar_id'],
                'student_id' => $validated['student_id'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
                'status' => $validated['status'] ?? 'active',
                'description' => $validated['description'] ?? null,
                'pengganti_dari_sesi_id' => $penggantiDariSesi?->id,
            ], $snapshot));
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000' && $penggantiDariSesi) {
                return redirect()
                    ->route('jadwal.kelas.index', ['student_id' => $validated['student_id']])
                    ->with('error', 'Sesi ini baru saja dibuatkan sesi pengganti oleh admin lain.');
            }

            throw $e;
        }

        // Kembali ke index yang sudah di-scope ke student itu (bukan
        // index global) — sesuai alur "ina": create -> kembali ke index
        // yang scoped ke parent-nya.
        return redirect()
            ->route('jadwal.kelas.index', ['student_id' => $validated['student_id']])
            ->with('success', $penggantiDariSesi
                ? 'Sesi pengganti berhasil dibuat.'
                : 'Jadwal Kelas berhasil ditambahkan.');
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

        // Snapshot SEBELUM update -- dipakai notifyPengajarScheduleChanged()
        // di bawah untuk tahu apakah waktu/pengajar-nya BENAR-BENAR
        // berubah (bukan cuma field lain seperti description/status),
        // dan untuk menyusun pesan "dari jam X jadi jam Y".
        $oldStartTime = $kelas->start_time;
        $oldEndTime = $kelas->end_time;
        $oldPengajarId = $kelas->pengajar_id;

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

        // Menutup gap yang didokumentasikan di CLAUDE.md item #15 spec
        // poin 11: notifikasi pengajar SEBELUMNYA cuma jalan lewat alur
        // approve/reject Reschedule Request (lihat
        // JadwalRescheduleRequestController::sendRescheduleNotifications()),
        // TIDAK jalan kalau admin edit jadwal langsung dari form Edit
        // biasa seperti di sini. Best-effort, tidak pernah menggagalkan
        // update() itu sendiri -- lihat notifyPengajarScheduleChanged().
        $sameStart = ($oldStartTime === null && $kelas->start_time === null)
            || ($oldStartTime && $kelas->start_time && $oldStartTime->eq($kelas->start_time));
        $sameEnd = ($oldEndTime === null && $kelas->end_time === null)
            || ($oldEndTime && $kelas->end_time && $oldEndTime->eq($kelas->end_time));
        $timeChanged = ! $sameStart || ! $sameEnd;
        $pengajarChanged = $oldPengajarId !== $kelas->pengajar_id;

        if ($timeChanged || $pengajarChanged) {
            $this->notifyPengajarScheduleChanged($kelas, $oldStartTime, $oldEndTime, $oldPengajarId);
        }

        return redirect()
            ->route('jadwal.kelas.index', ['student_id' => $kelas->student_id])
            ->with('success', 'Jadwal Kelas berhasil diperbarui.');
    }

    /**
     * Kirim notifikasi WA ke pengajar (pengajar BARU, kalau baris ini
     * juga dipindah ke pengajar lain) bahwa jadwalnya baru saja diubah
     * langsung lewat form Edit -- lihat update()'s docblock inline di
     * atas untuk gap yang ditutup ini. Pakai device & flag
     * `reschedule_notify_pengajar` yang SAMA dengan App\Models     * JadwalReminderSetting milik alur Reschedule Request (bukan
     * pengaturan/device terpisah) -- sesuai prinsip "semua by sistem,
     * jangan hardcode" (CLAUDE.md item #15 spec poin 10).
     */
    private function notifyPengajarScheduleChanged(JadwalKelas $kelas, ?\Carbon\Carbon $oldStartTime, ?\Carbon\Carbon $oldEndTime, ?string $oldPengajarId): void
    {
        $company = $kelas->company ?? \App\Models\Company::find($kelas->company_id);

        if (! $company || ! $this->packageLimits->hasActiveCategoryPackage($company, JadwalReminderSetting::CHAT_CATEGORY_NAMES)) {
            return;
        }

        $setting = JadwalReminderSetting::where('company_id', $company->id)->first();

        if (! $setting || ! $setting->device_id || ! $setting->reschedule_notify_pengajar) {
            return;
        }

        $pengajar = $kelas->pengajar ?? \App\Models\User::find($kelas->pengajar_id);
        $owner = $company->user;

        if (! $pengajar || ! $pengajar->handphone || ! $owner) {
            return;
        }

        $body = sprintf(
            "Jadwal mengajar Anda untuk %s (%s) diubah oleh admin.\nSebelumnya: %s\nSekarang: %s",
            $kelas->student?->name ?? '-',
            $kelas->mataPelajaran?->name ?? '-',
            $oldStartTime ? $oldStartTime->translatedFormat('d F Y, H:i').($oldEndTime ? '-'.$oldEndTime->format('H:i') : '') : '-',
            $kelas->start_time ? $kelas->start_time->translatedFormat('d F Y, H:i').($kelas->end_time ? '-'.$kelas->end_time->format('H:i') : '') : '-',
        );

        try {
            $token = $this->jwtService->mintFor($owner);
            $jid = PhoneNumber::normalize($pengajar->handphone).'@s.whatsapp.net';
            $this->inbox->send($token, $setting->device_id, $jid, $body);
        } catch (Throwable $e) {
            Log::warning('JadwalKelasController: gagal mengirim notifikasi perubahan jadwal ke pengajar', [
                'jadwal_kelas_id' => $kelas->id,
                'error' => $e->getMessage(),
            ]);
        }
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
     * Update cepat status kehadiran + keterangan dari index (tombol/
     * dropdown per baris -- lihat jadwal-kelas/index.blade.php), tanpa
     * lewat halaman Edit. Sengaja endpoint terpisah dari update() supaya
     * validasinya ringan (cuma 2 field ini) dan tidak menyentuh field
     * jadwal lain (waktu, pengajar, dst).
     */
    public function updateAttendance(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $kelas = $this->findOrFail($context, $id);

        $validated = $request->validate([
            'attendance_status' => ['nullable', 'in:'.implode(',', \App\Models\JadwalKelas::ATTENDANCE_STATUSES)],
            'attendance_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $kelas->update([
            'attendance_status' => $validated['attendance_status'] ?? null,
            'attendance_notes' => $validated['attendance_notes'] ?? null,
        ]);

        return back()->with('success', 'Status kehadiran "'.$kelas->student?->name.'" berhasil diperbarui.');
    }

    /**
     * Kirim ulang REKAP WA (bukan hanya sesi ini) ke pengajar sesi ini,
     * untuk tanggal sesi ini -- Jadwal v2 spec poin 10 ("admin bisa
     * klik kirim reminder kapan saja"), pakai device/template SAMA
     * dengan pengaturan reminder otomatis (App\Models\JadwalReminderSetting)
     * -- lihat App\Jobs\SendJadwalPengajarReminder & App\Services\
     * Jadwal\JadwalPengajarRecapService untuk kenapa satu jalur pesan
     * dipakai bersama oleh reminder otomatis, manual, dan request WA.
     * $forceResend=true supaya tetap terkirim walau H-1 otomatis untuk
     * pengajar+tanggal ini sudah pernah terkirim sebelumnya.
     */
    public function sendPengajarReminder(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $kelas = $this->findOrFail($context, $id);

        if (! $kelas->start_time) {
            return back()->with('error', 'Sesi ini belum punya waktu mulai, reminder tidak bisa dikirim.');
        }

        \App\Jobs\SendJadwalPengajarReminder::dispatch(
            $context->company->id,
            $kelas->pengajar_id,
            $kelas->start_time->toDateString(),
            forceResend: true,
        );

        return back()->with('success', 'Reminder sedang dikirim ke pengajar "'.($kelas->pengajar?->name ?? '-').'".');
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
            'teamMembers' => $this->companyPengajarMembers($context->company, $branchOfficeId),
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
            // Sesi pengganti (izin/sakit, spec poin 8) -- lihat
            // store()'s penanganan pengganti_dari_sesi_id.
            'pengganti_dari_sesi_id' => [
                'nullable', 'uuid', 'exists:jadwal_kelas,id',
                function ($attribute, $value, $fail) use ($company) {
                    if (! $value) {
                        return;
                    }

                    $original = JadwalKelas::where('company_id', $company->id)->where('id', $value)->first();

                    if (! $original) {
                        $fail('Sesi asli tidak valid.');

                        return;
                    }

                    if (JadwalKelas::where('pengganti_dari_sesi_id', $value)->exists()) {
                        $fail('Sesi ini sudah punya sesi pengganti.');
                    }
                },
            ],
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
