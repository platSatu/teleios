<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalBranchSetting;
use App\Models\JadwalMataPelajaran;
use App\Models\JadwalPengajarJadwal;
use App\Models\JadwalPengajarKategori;
use App\Models\JadwalRutin;
use App\Models\JadwalStudent;
use App\Services\Jadwal\JadwalRutinConflictService;
use App\Services\Jadwal\JadwalRutinSesiGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
 *
 * Update 3 September 2026 (permintaan user): kalau datang dengan
 * konteks Kategori+Pengajar (lewat tombol "+ Add Student" di index
 * Pengajar), form ini juga menampilkan CHECKLIST slot ketersediaan
 * Pengajar (App\Models\JadwalPengajarJadwal) yang MASIH KOSONG --
 * "kosong" = belum ada Jadwal Rutin aktif lain punya Pengajar yang
 * sama, bentrok jam ("1 kelas 1 guru = private", lihat App\Services\
 * Jadwal\JadwalRutinConflictService::findPengajarConflict(), rule yang
 * sama dipakai App\Http\Controllers\Jadwal\JadwalRutinController), DAN
 * masih dalam jam operasional branch (App\Models\JadwalBranchSetting).
 * Slot yang dicentang admin di form ini otomatis jadi baris
 * App\Models\JadwalRutin begitu Student disimpan (lihat store()),
 * durasi = rentang slot itu sendiri (mis. 10:00-12:00 = 120 menit),
 * DAN sesi bulan berjalan langsung digenerate saat itu juga lewat
 * App\Services\Jadwal\JadwalRutinSesiGenerator (bukan nunggu command
 * `jadwal:generate-sesi` yang jalan tiap tanggal 1) -- "checklist,
 * pilih slot, langsung auto-generate 4x sesi" sesuai permintaan user.
 * Kalau branch belum punya Jam Operasional, atau tidak ada slot yang
 * dicentang, Student tetap berhasil dibuat seperti biasa -- fitur ini
 * murni tambahan, bukan syarat wajib.
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
        // `kategori.mataPelajaran` ikut di-eager-load supaya branch-nya
        // bisa dipakai resolve JadwalBranchSetting di bawah, tanpa query
        // tambahan.
        $pengajarAvailability = ($kategoriId && $pengajarId)
            ? JadwalPengajarKategori::with(['jadwals', 'kategori.mataPelajaran'])
                ->where('company_id', $context->company->id)
                ->where('jadwal_kategori_id', $kategoriId)
                ->where('pengajar_id', $pengajarId)
                ->first()
            : null;

        $branchSetting = null;
        $openSlots = collect();

        if ($pengajarAvailability) {
            $branchOfficeId = $context->isLockedToBranch()
                ? $context->branchOffice?->id
                : $pengajarAvailability->kategori?->mataPelajaran?->branch_office_id;

            $branchSetting = $branchOfficeId
                ? JadwalBranchSetting::where('branch_office_id', $branchOfficeId)->first()
                : null;

            if ($branchSetting) {
                $openSlots = $this->openSlotsFor($context, $pengajarAvailability, $pengajarId, $branchSetting);
            }
        }

        return view('jadwal.jadwal-student.create', [
            'student' => null,
            'selectedMataPelajaranId' => $request->query('jadwal_mata_pelajaran_id'),
            'selectedPengajarId' => $pengajarId,
            'selectedKategoriId' => $kategoriId,
            'pengajarAvailability' => $pengajarAvailability,
            'branchSettingMissing' => $pengajarAvailability && ! $branchSetting,
            'openSlots' => $openSlots,
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

        $slotIds = array_values(array_filter((array) $request->input('jadwal_rutin_slot_ids', [])));

        [$student, $rutinCreated, $rutinSkipped] = DB::transaction(function () use ($company, $validated, $mataPelajaran, $kategoriId, $slotIds) {
            $student = JadwalStudent::create([
                'company_id' => $company->id,
                'branch_office_id' => $validated['branch_office_id'] ?? $mataPelajaran?->branch_office_id,
                'jadwal_mata_pelajaran_id' => $validated['jadwal_mata_pelajaran_id'],
                'pengajar_id' => $validated['pengajar_id'],
                'name' => $validated['name'],
                'parent_phone_number' => $validated['parent_phone_number'] ?? null,
                'student_phone_number' => $validated['student_phone_number'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            [$rutinCreated, $rutinSkipped] = $kategoriId && $slotIds
                ? $this->createRutinFromSlots($company, $student, $kategoriId, $validated['pengajar_id'], $slotIds)
                : [0, []];

            return [$student, $rutinCreated, $rutinSkipped];
        });

        $message = 'Student berhasil ditambahkan.';

        if ($rutinCreated > 0) {
            $message .= " {$rutinCreated} Jadwal Rutin otomatis dibuat dari slot yang dicentang, sesi bulan ini langsung digenerate.";
        }

        if (! empty($rutinSkipped)) {
            $message .= ' Slot yang dilewati: '.implode('; ', $rutinSkipped).'.';
        }

        return redirect()
            ->route('jadwal.student.index', array_filter([
                'jadwal_mata_pelajaran_id' => $validated['jadwal_mata_pelajaran_id'],
                'pengajar_id' => $validated['pengajar_id'],
                'jadwal_kategori_id' => $kategoriId,
            ]))
            ->with('success', $message);
    }

    /**
     * Buat App\Models\JadwalRutin untuk tiap CHUNK ketersediaan Pengajar
     * yang dicentang admin di form Add Student (lihat splitSlotIntoChunks()
     * & class docblock -- satu blok panjang App\Models\JadwalPengajarJadwal,
     * mis. Jumat 13:30-17:00, dipecah jadi beberapa sesi 30 menit sesuai
     * `durasi_sesi_default_menit` branch, masing-masing bisa dipilih murid
     * BEDA), lalu langsung generate sesi bulan berjalan lewat
     * App\Services\Jadwal\JadwalRutinSesiGenerator. Divalidasi ULANG dari
     * NOL di sini (bukan cuma percaya `$chunkIds` dari form -- lihat
     * parseChunkId()) -- kepemilikan slot induk (company+kategori+pengajar
     * cocok), chunk itu memang salah satu hasil pecahan yang sah dari slot
     * induknya (bukan jam sembarangan hasil tempering request), jam
     * operasional branch, DAN bentrok pengajar (race condition: chunk yang
     * kelihatan kosong saat form dibuka bisa saja baru saja terisi admin
     * lain sebelum submit ini).
     *
     * @param  array<int, string>  $chunkIds  Format tiap elemen: "{jadwal_pengajar_kategori_jadwal_id}|{H:i jam mulai chunk}" (lihat openSlotsFor()).
     * @return array{0: int, 1: array<int, string>} [jumlah Jadwal Rutin dibuat, daftar alasan chunk yang dilewati]
     */
    private function createRutinFromSlots(Company $company, JadwalStudent $student, string $kategoriId, string $pengajarId, array $chunkIds): array
    {
        $branchOfficeId = $student->branch_office_id;
        $branchSetting = $branchOfficeId
            ? JadwalBranchSetting::where('branch_office_id', $branchOfficeId)->first()
            : null;

        if (! $branchSetting) {
            return [0, ['branch belum punya Jam Operasional -- atur dulu lewat menu Jadwal > Branch > Jam Operasional']];
        }

        $parsed = array_filter(array_map([$this, 'parseChunkId'], $chunkIds));
        $parentSlotIds = array_values(array_unique(array_column($parsed, 'slot_id')));

        $parentSlots = JadwalPengajarJadwal::whereIn('id', $parentSlotIds)
            ->whereHas('pengajarKategori', function ($q) use ($company, $kategoriId, $pengajarId) {
                $q->where('company_id', $company->id)
                    ->where('jadwal_kategori_id', $kategoriId)
                    ->where('pengajar_id', $pengajarId);
            })
            ->get()
            ->keyBy('id');

        $conflictService = app(JadwalRutinConflictService::class);
        $generator = app(JadwalRutinSesiGenerator::class);
        $today = now()->toDateString();
        $durasiMenit = $branchSetting->durasi_sesi_default_menit;

        $created = 0;
        $skipped = [];

        foreach ($parsed as $item) {
            $parentSlot = $parentSlots->get($item['slot_id']);

            if (! $parentSlot) {
                // Slot induk tidak ditemukan/bukan milik kategori+pengajar
                // ini -- kemungkinan request di-tempering, lewati diam-diam
                // (tidak masuk pesan skip supaya tidak bocorin detail ke
                // pengguna yang mungkin memang lagi coba-coba).
                continue;
            }

            // Chunk yang diminta HARUS persis salah satu hasil pecahan sah
            // dari slot induknya -- bukan percaya begitu saja jam_mulai
            // yang dikirim form.
            $validChunk = collect($this->splitSlotIntoChunks(substr($parentSlot->jam_mulai, 0, 5), substr($parentSlot->jam_selesai, 0, 5), $durasiMenit))
                ->first(fn ($c) => $c['jam_mulai'] === $item['jam_mulai']);

            if (! $validChunk) {
                continue;
            }

            $jamMulai = $validChunk['jam_mulai'];
            $jamSelesai = $validChunk['jam_selesai'];
            $label = $parentSlot->hariLabel()." {$jamMulai}-{$jamSelesai}";

            if (! $branchSetting->isHariOperasional($parentSlot->hari) || ! $branchSetting->isWithinOperationalHours($jamMulai, $jamSelesai)) {
                $skipped[] = "{$label} (di luar jam operasional branch)";

                continue;
            }

            $conflict = $conflictService->findPengajarConflict(
                companyId: $company->id,
                hari: $parentSlot->hari,
                jamMulai: $jamMulai,
                jamSelesai: $jamSelesai,
                efektifMulai: $today,
                efektifSelesai: null,
                pengajarId: $pengajarId,
            );

            if ($conflict) {
                $skipped[] = "{$label} (baru saja terisi murid lain: ".($conflict->student?->name ?? '-').')';

                continue;
            }

            $rutin = JadwalRutin::create([
                'company_id' => $company->id,
                'branch_office_id' => $branchOfficeId,
                'student_id' => $student->id,
                'jadwal_kategori_id' => $kategoriId,
                'pengajar_id' => $pengajarId,
                'jadwal_ruangan_id' => null,
                'hari' => $parentSlot->hari,
                'jam_mulai' => $jamMulai,
                'durasi_menit' => $durasiMenit,
                'efektif_mulai' => $today,
                'efektif_selesai' => null,
                'status' => 'active',
            ]);

            $generator->generateForRutin($rutin);
            $created++;
        }

        return [$created, $skipped];
    }

    /**
     * Parse ID checkbox chunk ("{slotId}|{H:i}") jadi ['slot_id' =>
     * ..., 'jam_mulai' => ...], atau null kalau formatnya tidak valid
     * sama sekali (request di-tempering) -- dipakai createRutinFromSlots().
     *
     * @return array{slot_id: string, jam_mulai: string}|null
     */
    private function parseChunkId(string $chunkId): ?array
    {
        $parts = explode('|', $chunkId, 2);

        if (count($parts) !== 2 || $parts[0] === '' || ! preg_match('/^\d{2}:\d{2}$/', $parts[1])) {
            return null;
        }

        return ['slot_id' => $parts[0], 'jam_mulai' => $parts[1]];
    }

    /**
     * Pecah SATU blok ketersediaan Pengajar (mis. Jumat 13:30-17:00) jadi
     * beberapa chunk berdurasi tetap `$durasiMenit` (dari
     * App\Models\JadwalBranchSetting::durasi_sesi_default_menit) --
     * permintaan user: blok panjang yang dideklarasikan Pengajar BUKAN
     * satu sesi mingguan, tapi rentang yang bisa diisi murid BEDA-BEDA di
     * jam berbeda di dalam rentang itu, masing-masing sepanjang durasi
     * sesi default branch (mis. Jumat 13:30-14:00, 14:00-14:30, dst).
     * Sisa waktu yang kurang dari satu durasi penuh di ujung blok
     * (mis. blok 13:30-14:45 dengan durasi 30 menit -> chunk terakhir
     * cuma sampai 14:30, 15 menit sisanya tidak jadi chunk) SENGAJA tidak
     * diikutkan, supaya setiap chunk yang ditampilkan punya durasi penuh.
     *
     * @return list<array{jam_mulai: string, jam_selesai: string}>
     */
    private function splitSlotIntoChunks(string $jamMulai, string $jamSelesai, int $durasiMenit): array
    {
        if ($durasiMenit <= 0) {
            return [];
        }

        $chunks = [];
        $cursor = \Carbon\Carbon::createFromFormat('H:i', $jamMulai);
        $end = \Carbon\Carbon::createFromFormat('H:i', $jamSelesai);

        while ($cursor->copy()->addMinutes($durasiMenit)->lte($end)) {
            $chunkEnd = $cursor->copy()->addMinutes($durasiMenit);
            $chunks[] = ['jam_mulai' => $cursor->format('H:i'), 'jam_selesai' => $chunkEnd->format('H:i')];
            $cursor = $chunkEnd;
        }

        return $chunks;
    }

    /**
     * Chunk ketersediaan Pengajar (App\Models\JadwalPengajarJadwal, sudah
     * dipecah per durasi sesi default branch lewat splitSlotIntoChunks())
     * yang MASIH KOSONG -- dipakai create() untuk checklist di form Add
     * Student (lihat class docblock). Kriteria sama persis dengan yang
     * dicek ulang createRutinFromSlots() saat submit: dalam jam
     * operasional branch DAN tidak bentrok Jadwal Rutin aktif lain punya
     * Pengajar yang sama.
     *
     * @return Collection<int, array{id: string, hari: int, hari_label: string, jam_mulai: string, jam_selesai: string, durasi_menit: int}>
     */
    private function openSlotsFor($context, JadwalPengajarKategori $pengajarAvailability, string $pengajarId, JadwalBranchSetting $branchSetting): Collection
    {
        $conflictService = app(JadwalRutinConflictService::class);
        $today = now()->toDateString();
        $durasiMenit = $branchSetting->durasi_sesi_default_menit;

        return $pengajarAvailability->jadwals
            ->flatMap(function (JadwalPengajarJadwal $slot) use ($durasiMenit) {
                return collect($this->splitSlotIntoChunks(substr($slot->jam_mulai, 0, 5), substr($slot->jam_selesai, 0, 5), $durasiMenit))
                    ->map(fn ($chunk) => [
                        'slot_id' => $slot->id,
                        'hari' => $slot->hari,
                        'hari_label' => $slot->hariLabel(),
                        'jam_mulai' => $chunk['jam_mulai'],
                        'jam_selesai' => $chunk['jam_selesai'],
                    ]);
            })
            ->filter(fn (array $chunk) => $branchSetting->isHariOperasional($chunk['hari'])
                && $branchSetting->isWithinOperationalHours($chunk['jam_mulai'], $chunk['jam_selesai']))
            ->reject(fn (array $chunk) => (bool) $conflictService->findPengajarConflict(
                companyId: $context->company->id,
                hari: $chunk['hari'],
                jamMulai: $chunk['jam_mulai'],
                jamSelesai: $chunk['jam_selesai'],
                efektifMulai: $today,
                efektifSelesai: null,
                pengajarId: $pengajarId,
            ))
            ->map(fn (array $chunk) => [
                'id' => "{$chunk['slot_id']}|{$chunk['jam_mulai']}",
                'hari' => $chunk['hari'],
                'hari_label' => $chunk['hari_label'],
                'jam_mulai' => $chunk['jam_mulai'],
                'jam_selesai' => $chunk['jam_selesai'],
                'durasi_menit' => $durasiMenit,
            ])
            ->values();
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
