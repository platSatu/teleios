<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalBranchSetting;
use App\Models\JadwalKelas;
use App\Models\JadwalMataPelajaran;
use App\Models\JadwalReminderSetting;
use App\Models\JadwalRuangan;
use App\Models\JadwalStudent;
use App\Services\Chat\InboxService;
use App\Services\Chat\SystemJwtService;
use App\Services\PackageLimitService;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

/**
 * CRUD for "Jadwal Kelas" (Jadwal > Jadwal Kelas) — satu baris per sesi
 * kelas: pengajar, murid (App\Models\JadwalStudent, opsional -- lihat
 * docblock App\Models\JadwalKelas soal "slot kosong"), dan rentang
 * waktunya, opsional terhubung ke satu App\Models\JadwalMataPelajaran
 * & satu App\Models\JadwalRuangan.
 *
 * index() -- riwayat 2 iterasi di sesi yang sama, 7 September 2026:
 * awalnya tabel flat paginated, sempat diganti jadi grid slot 30 menit
 * per Ruangan/Pengajar (tab + baris kosong utk tiap slot waktu), lalu
 * SETELAH DIDISKUSIKAN LAGI dengan user, balik ke tabel FLAT
 * paginated (bukan grid) tapi dengan FILTER: Tanggal (default hari
 * ini, bisa dikosongkan utk lihat semua tanggal), Pengajar, Mata
 * Pelajaran/Bidang -- permintaan eksplisit user: "tampilkan semua
 * guru, semua ruangan, dan student ... tampilannya begitu saja, by
 * filter ... logic-nya juga gampang tampilkan data relasinya pakai
 * data table ya dan button action saja". Satu baris = satu
 * App\Models\JadwalKelas yang BENERAN ADA (tidak ada baris slot
 * kosong buatan seperti grid versi sebelumnya) -- kolom: Pengajar,
 * Ruangan, Bidang, Kategori, Murid, Mulai, Selesai, Kehadiran,
 * Status, Aksi. Diurutkan ascending by start_time kalau filter
 * Tanggal diisi (kronologis 1 hari), descending kalau tidak (aktivitas
 * terbaru dulu). Filter dibawa lewat query string (pola sama dengan
 * JadwalStudentController::index()) & lewat hidden input ke
 * create()/edit() supaya admin balik ke filter yang sama setelah
 * simpan/hapus -- lihat filterRedirectParams().
 *
 * Jam sesi manual di sini (start_time/end_time) SEKARANG divalidasi
 * terhadap Jam Operasional branch (App\Models\JadwalBranchSetting) --
 * lihat validator()'s $validator->after() closure, permintaan user
 * yang sama ("jam guru input itu harus sama dengan jam operasional
 * branch"). Dilewati (tidak ditolak) kalau start_time kosong (slot
 * kosong) atau branch belum punya Jam Operasional diatur sama sekali
 * -- tidak ada dasar buat menolak kalau memang belum ada aturannya.
 *
 * create() mengunci field locked-vs-free seperti sebelumnya (branch,
 * mata pelajaran, pengajar, student kalau datang lengkap dari index
 * Student) -- lihat JadwalKelasController::create()'s inline docblock
 * untuk detail.
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

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $request->query('branch_office_id');

        // Filter -- lihat class docblock update 7 September 2026.
        // `date` default hari ini kalau query-nya belum ada SAMA SEKALI
        // (kunjungan pertama); tetap dihormati kalau admin sengaja
        // mengosongkan field-nya lalu submit (query ada tapi string
        // kosong -- lihat pengecekan `if ($date)` di bawah, jadi berarti
        // "semua tanggal").
        $date = $request->query('date', now()->toDateString());
        $pengajarId = $request->query('pengajar_id');
        $mataPelajaranId = $request->query('jadwal_mata_pelajaran_id');

        $query = JadwalKelas::where('company_id', $company->id)
            ->with([
                'pengajar:id,name', 'student:id,name', 'mataPelajaran:id,name', 'kategori:id,name',
                'ruangan:id,name',
                'sesiPengganti:id,pengganti_dari_sesi_id,start_time',
                'penggantiDariSesi:id,start_time',
            ]);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        } elseif ($branchOfficeId) {
            $query->where('branch_office_id', $branchOfficeId);
        }

        if ($date) {
            $query->whereDate('start_time', $date);
        }

        if ($pengajarId) {
            $query->where('pengajar_id', $pengajarId);
        }

        if ($mataPelajaranId) {
            $query->where('jadwal_mata_pelajaran_id', $mataPelajaranId);
        }

        // Kronologis (ascending) kalau lagi lihat 1 tanggal spesifik --
        // paling masuk akal buat agenda 1 hari. Tanpa filter tanggal
        // (semua tanggal), descending (aktivitas terbaru dulu) lebih
        // berguna daripada mulai dari baris tertua di halaman pertama.
        $query->orderByRaw('start_time IS NULL, start_time '.($date ? 'asc' : 'desc'));

        $sesiList = $query->paginate(20)->withQueryString()->onEachSide(1);

        return view('jadwal.jadwal-kelas.index', [
            'sesiList' => $sesiList,
            'pengajars' => $this->companyPengajarMembers($company, $branchOfficeId),
            'mataPelajarans' => JadwalMataPelajaran::where('company_id', $company->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'filterDate' => $date,
            'filterPengajarId' => $pengajarId,
            'filterMataPelajaranId' => $mataPelajaranId,
            'branchOfficeId' => $branchOfficeId,
        ]);
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        $penggantiDariSesi = $request->query('pengganti_dari_sesi_id')
            ? $this->findOrFail($context, $request->query('pengganti_dari_sesi_id'))
            : null;

        // ruangan_id + start_time (prefill, BUKAN dikunci -- start_time
        // tetap boleh digeser admin) -- lihat class docblock.
        return view('jadwal.jadwal-kelas.create', [
            'kelas' => null,
            'selectedBranchOfficeId' => $request->query('branch_office_id') ?? $penggantiDariSesi?->branch_office_id,
            'selectedMataPelajaranId' => $request->query('jadwal_mata_pelajaran_id') ?? $penggantiDariSesi?->jadwal_mata_pelajaran_id,
            'selectedPengajarId' => $request->query('pengajar_id') ?? $penggantiDariSesi?->pengajar_id,
            'selectedStudentId' => $request->query('student_id') ?? $penggantiDariSesi?->student_id,
            'selectedRuanganId' => $request->query('ruangan_id') ?? $penggantiDariSesi?->jadwal_ruangan_id,
            'prefillStartTime' => $request->query('start_time'),
            // Filter index() yang aktif waktu admin klik "Tambah" --
            // dibawa balik lewat hidden input `date` (tidak ada field
            // 'date' asli di form ini, cuma datetime-local start_time/
            // end_time) supaya link "Kembali"/"Batal" balik ke filter
            // yang sama. Filter Pengajar/Mata Pelajaran cukup dibaca
            // dari FIELD form asli (pengajar_id/jadwal_mata_pelajaran_id
            // sudah ada di form ini juga) -- lihat filterRedirectParams().
            'returnDate' => $request->query('date'),
            'returnPengajarId' => $request->query('pengajar_id'),
            'returnMataPelajaranId' => $request->query('jadwal_mata_pelajaran_id'),
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
                    'ruangan_id', 'start_time', 'date',
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
            $kelas = JadwalKelas::create(array_merge([
                'company_id' => $company->id,
                'branch_office_id' => $validated['branch_office_id'] ?? null,
                'jadwal_mata_pelajaran_id' => $validated['jadwal_mata_pelajaran_id'] ?? null,
                'jadwal_ruangan_id' => $validated['jadwal_ruangan_id'] ?? null,
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
                    ->route('jadwal.kelas.index', $this->filterRedirectParams($penggantiDariSesi))
                    ->with('error', 'Sesi ini baru saja dibuatkan sesi pengganti oleh admin lain.');
            }

            throw $e;
        }

        // Kembali ke list (filter Tanggal/Pengajar/Mata Pelajaran) yang
        // relevan dengan sesi baru ini -- lihat class docblock &
        // filterRedirectParams().
        return redirect()
            ->route('jadwal.kelas.index', $this->filterRedirectParams($kelas))
            ->with('success', $penggantiDariSesi
                ? 'Sesi pengganti berhasil dibuat.'
                : 'Jadwal Kelas berhasil ditambahkan.');
    }

    /**
     * Route params untuk balik ke index() (list + filter) yang relevan
     * dengan $kelas ini, setelah create/update/destroy -- lihat class
     * docblock. Filter Tanggal ikut start_time sesi ini kalau ada,
     * DIKOSONGKAN ('', bukan tanggal hari ini) kalau sesi ini belum
     * ada jamnya sama sekali -- supaya sesi "slot kosong" itu tetap
     * kelihatan di list setelahnya (filter Tanggal defaultnya "hari
     * ini" di index(), yang bakal menyaring sesi tanpa start_time
     * hilang dari tampilan kalau tidak dikosongkan eksplisit di sini).
     */
    private function filterRedirectParams(JadwalKelas $kelas): array
    {
        return [
            'date' => $kelas->start_time?->toDateString() ?? '',
            'pengajar_id' => $kelas->pengajar_id ?? '',
            'jadwal_mata_pelajaran_id' => $kelas->jadwal_mata_pelajaran_id ?? '',
        ];
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $kelas = $this->findOrFail($context, $id);

        // TIDAK mengunci field apa pun di sini (selalu dropdown bebas) --
        // locking hanya berlaku di create(), sama seperti pola "ina"
        // project's University Album Photo edit() (lihat class docblock).
        // Link "Kembali"/"Batal" balik ke filter yang relevan dengan
        // sesi ini APA ADANYA (sebelum diedit) -- lihat
        // filterRedirectParams(), dihitung langsung di blade dari
        // $kelas, tidak perlu dibawa lewat query di sini.
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
            'jadwal_ruangan_id' => $validated['jadwal_ruangan_id'] ?? null,
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
            ->route('jadwal.kelas.index', $this->filterRedirectParams($kelas))
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
        $redirectParams = $this->filterRedirectParams($kelas);
        $kelas->delete();

        return redirect()
            ->route('jadwal.kelas.index', $redirectParams)
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
            // Ruangan (opsional, lihat docblock App\Models\JadwalRuangan)
            // -- dipakai dropdown Ruangan di form ini.
            'ruangans' => JadwalRuangan::where('company_id', $context->company->id)
                ->where('status', JadwalRuangan::STATUS_ACTIVE)
                ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
                ->orderBy('name')
                ->get(['id', 'name', 'branch_office_id']),
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
        // <select name="student_id"> yang dikosongkan (opsi "- Slot
        // Kosong -") kirim string kosong, bukan absen -- normalisasi
        // ke null di sini SEBELUM validasi supaya lolos rule
        // 'nullable' dan tidak nyoba insert '' ke kolom FK char(36)
        // (lihat docblock App\Models\JadwalKelas soal slot kosong).
        if ($request->has('student_id') && $request->input('student_id') === '') {
            $request->merge(['student_id' => null]);
        }
        if ($request->has('jadwal_ruangan_id') && $request->input('jadwal_ruangan_id') === '') {
            $request->merge(['jadwal_ruangan_id' => null]);
        }

        $validator = Validator::make($request->all(), [
            'jadwal_mata_pelajaran_id' => [
                'nullable', 'uuid', 'exists:jadwal_mata_pelajaran,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalMataPelajaran::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Mata Pelajaran / Bidang tidak valid.');
                    }
                },
            ],
            'pengajar_id' => ['required', 'uuid', 'exists:users,id'],
            // Nullable (spec: "slot kosong") -- pengajar+jam+ruangan
            // sudah dibuat manual, murid belum ditentukan. Lihat
            // migration make_student_id_nullable_on_jadwal_kelas_table.php
            // & docblock App\Models\JadwalKelas. String kosong dari
            // <select> dinormalisasi jadi null di atas, sebelum sampai
            // sini (FK char(36) tidak boleh diisi '').
            'student_id' => [
                'nullable', 'uuid', 'exists:jadwal_student,id',
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
            // Nullable (opsional, lihat docblock App\Models\JadwalRuangan) --
            // sesi bisa belum ditentukan ruangannya, tampil di tab
            // "Tanpa Ruangan" pada index() grid.
            'jadwal_ruangan_id' => [
                'nullable', 'uuid', 'exists:jadwal_ruangan,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalRuangan::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Ruangan tidak valid.');
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

        // Update 7 September 2026 (permintaan user): jam sesi yang
        // diinput manual di sini HARUS berada dalam Jam Operasional
        // branch (App\Models\JadwalBranchSetting) -- sebelumnya cuma
        // dicek untuk alur auto-generate dari ketersediaan Pengajar
        // (lihat App\Http\Controllers\Jadwal\JadwalStudentController),
        // TIDAK untuk input manual di sini. Pakai $validator->after()
        // (bukan rule per-field) karena butuh 2 field sekaligus
        // (branch_office_id + start_time). Dilewati (tidak divalidasi,
        // BUKAN ditolak) kalau start_time kosong (slot kosong, memang
        // belum ada jam) atau branch_office_id kosong/belum punya Jam
        // Operasional diatur -- tidak ada dasar untuk menolak kalau
        // memang belum ada aturan jamnya.
        $validator->after(function ($v) use ($request) {
            $startTimeRaw = $request->input('start_time');

            if (! $startTimeRaw) {
                return;
            }

            $branchOfficeId = $request->input('branch_office_id');
            $branchSetting = $branchOfficeId
                ? JadwalBranchSetting::where('branch_office_id', $branchOfficeId)->first()
                : null;

            if (! $branchSetting) {
                return;
            }

            try {
                $start = Carbon::parse($startTimeRaw);
            } catch (\Throwable) {
                return; // format tanggal tidak valid -- biar rule 'date' di atas yang menolak
            }

            $endTimeRaw = $request->input('end_time');
            $end = $endTimeRaw ? Carbon::parse($endTimeRaw) : $start->copy()->addMinutes((int) ($branchSetting->durasi_sesi_default_menit ?: 30));

            if (! $branchSetting->isHariOperasional($start->dayOfWeek)) {
                $v->errors()->add('start_time', 'Hari '.$start->translatedFormat('l').' bukan hari operasional branch ini.');

                return;
            }

            if (! $branchSetting->isWithinOperationalHours($start->format('H:i'), $end->format('H:i'))) {
                $v->errors()->add('start_time', sprintf(
                    'Jam sesi (%s-%s) di luar jam operasional branch (%s-%s)%s.',
                    $start->format('H:i'),
                    $end->format('H:i'),
                    substr($branchSetting->jam_buka, 0, 5),
                    substr($branchSetting->jam_tutup, 0, 5),
                    ($branchSetting->jam_istirahat_mulai && $branchSetting->jam_istirahat_selesai)
                        ? ' atau menabrak jam istirahat ('.substr($branchSetting->jam_istirahat_mulai, 0, 5).'-'.substr($branchSetting->jam_istirahat_selesai, 0, 5).')'
                        : ''
                ));
            }
        });

        return $validator;
    }
}
