<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalBranchSetting;
use App\Models\JadwalKategori;
use App\Models\JadwalKelas;
use App\Models\JadwalMataPelajaran;
use App\Models\JadwalPengajarJadwal;
use App\Models\JadwalPengajarKategori;
use App\Models\JadwalRuangan;
use App\Models\JadwalRutin;
use App\Models\JadwalStudent;
use App\Services\Jadwal\JadwalCountsService;
use App\Services\Jadwal\JadwalRutinConflictService;
use App\Services\Jadwal\JadwalRutinSesiGenerator;
use App\Services\Jadwal\JadwalScheduleChangeNotifier;
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

    /**
     * Update 4 September 2026 (laporan user: "ketika ada perubahan
     * jadwal wa nya terkirim 1x saja ya tidak tiap ada perubahan ya"):
     * App\Services\Jadwal\JadwalScheduleChangeNotifier di-constructor-
     * inject BARU di sini (sebelumnya tiap pemanggilan di controller ini
     * pakai `app(JadwalScheduleChangeNotifier::class)` langsung, resolve
     * instance BARU tiap kali) -- WAJIB satu instance yang SAMA dipakai
     * sepanjang satu request supaya antrean WA yang ditampung
     * rutinRemoved()/rutinAdded(batch: true) di update()/deactivate()
     * bisa di-flush jadi SATU pesan per Pengajar lewat
     * flushPengajarNotifications() (service ini bukan singleton -- lihat
     * docblock class-nya untuk detail penuh kenapa resolve ulang bikin
     * antrean itu "hilang").
     */
    public function __construct(
        protected JadwalScheduleChangeNotifier $scheduleChangeNotifier,
        protected JadwalCountsService $countsService,
    ) {
    }

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

        // Update 4 September 2026 (permintaan user: "tampilkan category
        // nya di ... index nya student"): Student TIDAK menyimpan
        // jadwal_kategori_id (lihat class docblock), jadi kolom
        // "Kategori" di index ini DI-DERIVE dari App\Models\JadwalRutin
        // AKTIF milik Student itu (bisa lebih dari satu Kategori kalau
        // Student punya beberapa Jadwal Rutin di Kategori berbeda,
        // ditampilkan sebagai badge terpisah -- lihat index.blade.php).
        //
        // Refactor 5 September 2026 (permintaan user: "kode makin
        // gemuk, tolong dirapikan") -- query ini dipindah apa adanya ke
        // App\Services\Jadwal\JadwalCountsService::activeKategoriNamesByStudent()
        // (SATU sumber dipakai bersama menu Pengajar/Mata Pelajaran,
        // lihat docblock class itu).
        $studentIds = collect($students->items())->pluck('id');
        $kategoriNamesByStudent = $this->countsService->activeKategoriNamesByStudent($company->id, $studentIds);

        foreach ($students as $student) {
            $student->setAttribute('kategori_names', $kategoriNamesByStudent->get($student->id, collect()));
        }

        // Konteks (kalau index ini dibuka scoped dari index Pengajar) —
        // dipakai untuk breadcrumb + tombol "+ Add Student" yang tetap
        // membawa konteksnya.
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
        // Fix 5 September 2026 (lihat docblock pengajarSlotsPanel()) --
        // dipindah lebih awal (sebelumnya dibaca di bawah, dekat
        // `$lockedMataPelajaranForBranch`) supaya bisa dipakai untuk
        // MEMFILTER checklist Kategori pengajar di bawah ke Bidang ini
        // saja, bukan cuma untuk resolve branch.
        $mataPelajaranId = $request->query('jadwal_mata_pelajaran_id');

        // Update 4 September 2026 (bug fix lanjutan, laporan user: "pada
        // form tambah student tidak keluar ya jadwal pengajar nya") --
        // SEBELUMNYA panel ketersediaan Pengajar cuma muncul kalau
        // `jadwal_kategori_id` DAN `pengajar_id` datang SEKALIGUS lewat
        // query string (drill-down penuh Kategori->Pengajar->
        // "+ Add Student" di index Pengajar, lihat
        // JadwalPengajarController::index()) -- akses lain ke Tambah
        // Student (menu sidebar langsung, atau habis klik "Ganti
        // Pengajar"/"Ganti Mata Pelajaran / Bidang" di _form.blade.php)
        // TIDAK PERNAH menampilkan apa-apa sama sekali walau admin
        // sudah pilih Pengajar di dropdown bebas -- beda dari Edit
        // Student yang dropdown Pengajar-nya sendiri sudah bisa reload
        // checklist (lihat pengajarSlotsPanel()).
        //
        // Sekarang disatukan jadi SATU query yang menangani DUA
        // skenario sekaligus: kalau `jadwal_kategori_id` ada (drill-down
        // penuh), difilter ke SATU baris JadwalPengajarKategori itu saja
        // (persis perilaku lama); kalau tidak ada (dropdown Pengajar
        // bebas, lihat `$pengajarLocked` & onchange reload baru di
        // _form.blade.php), tampilkan SEMUA Kategori yang Pengajar itu
        // ajar -- pola yang sama dengan pengajarSlotsPanel() punya Edit
        // Student. Jam Operasional di-resolve PER BARIS (bisa beda
        // branch antar Kategori kalau Pengajar itu ngajar lintas Mata
        // Pelajaran/branch) -- BEDA dari Edit Student yang punya SATU
        // sumber (`$student->branch_office_id`, karena Student-nya
        // sudah ada) -- create() belum ada Student yang committed jadi
        // tidak ada satu branch tunggal untuk dipakai semua baris.
        // Prioritas resolusi: context->branchOffice (kalau company
        // di-lock ke satu branch), fallback ke branch milik Mata
        // Pelajaran Kategori itu sendiri.
        $pengajarKategoris = collect();

        if ($pengajarId) {
            $pengajarKategoris = JadwalPengajarKategori::with(['jadwals', 'kategori.mataPelajaran'])
                ->where('company_id', $context->company->id)
                ->where('pengajar_id', $pengajarId)
                ->where('status', 'active')
                ->when($kategoriId, fn ($q) => $q->where('jadwal_kategori_id', $kategoriId))
                // Fix 5 September 2026 (lihat docblock
                // pengajarSlotsPanel()/kategoriBelongsToMataPelajaran()):
                // kalau tidak datang dari drill-down penuh
                // ($kategoriId kosong) TAPI Bidang sudah dipilih di
                // dropdown bebas ($mataPelajaranId ada), tetap batasi
                // ke Kategori yang anak Bidang itu -- jangan tampilkan
                // Kategori Pengajar ini dari Bidang lain.
                ->when(! $kategoriId && $mataPelajaranId, fn ($q) => $q->whereHas('kategori', fn ($qq) => $qq->where('jadwal_mata_pelajaran_id', $mataPelajaranId)))
                ->get();

            foreach ($pengajarKategoris as $pk) {
                $branchOfficeId = $context->isLockedToBranch()
                    ? $context->branchOffice?->id
                    : $pk->kategori?->mataPelajaran?->branch_office_id;

                $branchSetting = $branchOfficeId
                    ? JadwalBranchSetting::where('branch_office_id', $branchOfficeId)->first()
                    : null;

                // Update 4 September 2026 (permintaan user): slot yang
                // sudah kepakai murid lain sekarang TETAP ikut di daftar
                // (ditandai `taken` => true), bukan difilter hilang
                // seperti sebelumnya -- lihat docblock slotsFor(). Admin
                // jadi tahu jam itu memang ditawarkan pengajar tapi
                // sudah terisi, bukan mengira jam itu tidak pernah ada.
                $pk->setAttribute('slots', $branchSetting
                    ? $this->slotsFor($context, $pk, $pengajarId, $branchSetting)
                    : collect());
                $pk->setAttribute('branchSettingMissing', ! $branchSetting);
            }
        }

        // Update 4 September 2026 (permintaan user): urutan drill-down
        // Jadwal itu Branch -> Ruangan -> Jam Operasional -> Mata
        // Pelajaran / Bidang -> Kategori -> Pengajar -> Student -- pada
        // titik ini branch SEHARUSNYA sudah ditentukan lewat Mata
        // Pelajaran / Bidang yang dipilih, jadi kalau create() datang
        // dengan `jadwal_mata_pelajaran_id` di query string (drill-down),
        // branch ikut dikunci dari branch_office_id milik Mata Pelajaran
        // itu -- BUKAN dari query string `branch_office_id` (supaya
        // tidak bisa di-tempering beda dari Mata Pelajaran yang
        // sebenarnya dipilih). Kalau Mata Pelajaran itu sendiri tidak
        // terikat branch manapun (branch_office_id null), tidak ada yang
        // dikunci -- tetap dropdown bebas seperti sebelumnya. Akses
        // langsung tanpa drill-down (lewat menu sidebar "Student") juga
        // tidak terpengaruh, $mataPelajaranId bakal null. (Dibaca lebih
        // awal sekarang, lihat komentar dekat $kategoriId/$pengajarId
        // di atas.)
        $lockedMataPelajaranForBranch = $mataPelajaranId
            ? JadwalMataPelajaran::where('company_id', $context->company->id)->where('id', $mataPelajaranId)->first()
            : null;

        return view('jadwal.jadwal-student.create', [
            'student' => null,
            'selectedBranchOfficeId' => $lockedMataPelajaranForBranch?->branch_office_id,
            'selectedMataPelajaranId' => $mataPelajaranId,
            'selectedPengajarId' => $pengajarId,
            'selectedKategoriId' => $kategoriId,
            // Update 4 September 2026: Pengajar HANYA dikunci (disabled)
            // di skenario drill-down PENUH (Kategori+Pengajar
            // sekaligus) -- kalau cuma `pengajar_id` saja yang ada
            // (mis. dari onchange reload dropdown bebas, lihat
            // _form.blade.php), field-nya tetap dropdown bebas supaya
            // admin bisa ganti-ganti Pengajar & lihat checklist masing-
            // masing tanpa harus klik "Ganti Pengajar" dulu.
            'pengajarLocked' => (bool) ($kategoriId && $pengajarId),
            'previewPengajarId' => $pengajarId,
            'pengajarKategoris' => $pengajarKategoris,
            // Update 4 September 2026: belum ada Student, jadi tidak ada
            // Ruangan "sekarang" untuk di-preselect -- lihat komentar
            // panjang di edit() soal $selectedRuanganId/$ruanganMixed
            // (di situ DIDERIVE dari Jadwal Rutin aktif Student, tidak
            // relevan sama sekali di create()).
            'selectedRuanganId' => null,
            'ruanganMixed' => false,
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

        // Update 4 September 2026 (bug fix lanjutan -- lihat komentar
        // create()): checklist sekarang bisa berisi BANYAK Kategori
        // sekaligus (Pengajar bebas dipilih tanpa drill-down penuh),
        // jadi dikirim terkelompok `jadwal_rutin_slot_ids[<jadwal_
        // kategori_id>][]` -- format yang SAMA dipakai update() (lihat
        // di situ). `$kategoriId` (hidden field, cuma ke-isi kalau
        // datang dari drill-down penuh) TETAP dipakai untuk konteks
        // redirect di bawah, TAPI TIDAK LAGI dipakai untuk proses slot
        // -- proses slot sekarang generik per grup, sama seperti
        // update().
        $slotIdsByKategori = (array) $request->input('jadwal_rutin_slot_ids', []);
        // Update 4 September 2026 (permintaan user, kolom Ruangan baru
        // di form Tambah Student): diterapkan ke SEMUA Jadwal Rutin yang
        // dibuat dari checklist di bawah, lihat docblock
        // createRutinFromSlots() untuk detail pengecekan bentroknya.
        $ruanganId = $validated['jadwal_ruangan_id'] ?? null;

        [$student, $rutinCreated, $rutinSkipped] = DB::transaction(function () use ($company, $validated, $mataPelajaran, $slotIdsByKategori, $ruanganId) {
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

            $created = 0;
            $skipped = [];

            foreach ($slotIdsByKategori as $kid => $chunkIds) {
                $chunkIds = array_values(array_filter((array) $chunkIds));

                if (! $kid || ! $chunkIds) {
                    continue;
                }

                // Fix 5 September 2026 -- lihat docblock
                // kategoriBelongsToMataPelajaran(): jangan pernah bikin
                // JadwalRutin dari Kategori yang bukan anak Bidang yang
                // baru saja dipilih di Student ini.
                if (! $this->kategoriBelongsToMataPelajaran($company->id, (string) $kid, $validated['jadwal_mata_pelajaran_id'])) {
                    $skipped[] = 'Slot di bawah Kategori yang bukan bagian dari Mata Pelajaran / Bidang yang dipilih dilewati (tidak disimpan).';

                    continue;
                }

                [$c, $s] = $this->createRutinFromSlots($company, $student, (string) $kid, $validated['pengajar_id'], $chunkIds, $ruanganId);
                $created += $c;
                $skipped = array_merge($skipped, $s);
            }

            return [$student, $created, $skipped];
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
     * @param  array<int, string>  $chunkIds  Format tiap elemen: "{jadwal_pengajar_kategori_jadwal_id}|{H:i jam mulai chunk}" (lihat slotsFor()).
     * @param  string|null  $ruanganId  Update 4 September 2026 (permintaan user, kolom Ruangan baru di form Student): Ruangan yang dipilih admin di dropdown Ruangan Tambah/Edit Student, diterapkan ke SEMUA baris Jadwal Rutin baru yang dibuat dari sini (null = "Tanpa Ruangan", perilaku lama). Dicek bentrok Ruangan juga (bukan cuma Pengajar) lewat JadwalRutinConflictService::findRuanganConflict() -- dua murid beda Pengajar sekalipun tidak boleh dipasang ke Ruangan fisik yang sama di jam yang bentrok, sama prinsip yang sudah dipakai App\Http\Controllers\Jadwal\JadwalRutinController untuk alur manual.
     * @param  string|null  $changedBy  Update 4 September 2026 (laporan user: notifikasi WA ke Pengajar + histori before/after, lihat App\Services\Jadwal\JadwalScheduleChangeNotifier's docblock) -- SENGAJA dipakai dobel fungsi sebagai "siapa yang mengubah" SEKALIGUS "apakah perlu notifikasi/log sama sekali": non-null (dikirim update(), admin yang sedang login) = ya, kirim WA + tulis JadwalChangeLog; null (default, dipakai store() waktu bikin Student BARU) = tidak -- murid baru bukan "perubahan jadwal", jadi sengaja tidak ikut memicu notifikasi/log ini (tetap scoped ke laporan bug-nya: jadwal murid yang SUDAH ADA berubah).
     * @return array{0: int, 1: array<int, string>} [jumlah Jadwal Rutin dibuat, daftar alasan chunk yang dilewati]
     */
    private function createRutinFromSlots(Company $company, JadwalStudent $student, string $kategoriId, string $pengajarId, array $chunkIds, ?string $ruanganId = null, ?string $changedBy = null): array
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

            // Murid yang SAMA dikecualikan dari "bentrok" -- dipakai
            // Edit Student (lihat pengajarSlotsPanel()/slotsFor()), di
            // mana panel bisa menampilkan slot yang sebenarnya sudah
            // jadi Jadwal Rutin murid itu sendiri lewat Kategori lain.
            // create() tidak terpengaruh (murid baru, tidak mungkin
            // sudah punya Jadwal Rutin apa pun).
            if ($conflict && $conflict->student_id === $student->id) {
                // Update 4 September 2026 (bug fix): chunk ini SUDAH
                // jadi Jadwal Rutin aktif murid yang sama (ditandai
                // `mine` => true di slotsFor()) -- checkbox-nya
                // disabled+tercentang di checklist (browser TIDAK
                // pernah ikut mengirim id checkbox yang disabled), jadi
                // titik ini normalnya cuma kena kalau request
                // di-tempering. Jangan bikin duplikat Jadwal Rutin
                // untuk slot yang sudah ada -- lewati diam-diam (bukan
                // error, memang sudah sesuai keadaan yang diminta).
                continue;
            }

            if ($conflict && $conflict->student_id !== $student->id) {
                $skipped[] = "{$label} (baru saja terisi murid lain: ".($conflict->student?->name ?? '-').')';

                continue;
            }

            // Update 4 September 2026 (permintaan user, kolom Ruangan
            // baru): cek bentrok Ruangan juga kalau admin memilih satu --
            // beda Pengajar TETAP tidak boleh dipasang ke Ruangan fisik
            // yang sama di jam yang bentrok. Murid yang sama dikecualikan
            // (pola sama seperti pengecualian bentrok Pengajar di atas).
            if ($ruanganId) {
                $ruanganConflict = $conflictService->findRuanganConflict(
                    companyId: $company->id,
                    hari: $parentSlot->hari,
                    jamMulai: $jamMulai,
                    jamSelesai: $jamSelesai,
                    efektifMulai: $today,
                    efektifSelesai: null,
                    jadwalRuanganId: $ruanganId,
                );

                if ($ruanganConflict && $ruanganConflict->student_id !== $student->id) {
                    $skipped[] = "{$label} (Ruangan sudah dipakai murid lain: ".($ruanganConflict->student?->name ?? '-').')';

                    continue;
                }
            }

            $rutin = JadwalRutin::create([
                'company_id' => $company->id,
                'branch_office_id' => $branchOfficeId,
                'student_id' => $student->id,
                'jadwal_kategori_id' => $kategoriId,
                'pengajar_id' => $pengajarId,
                'jadwal_ruangan_id' => $ruanganId,
                'hari' => $parentSlot->hari,
                'jam_mulai' => $jamMulai,
                'durasi_menit' => $durasiMenit,
                'efektif_mulai' => $today,
                'efektif_selesai' => null,
                'status' => 'active',
            ]);

            $generator->generateForRutin($rutin);
            $created++;

            // Lihat docblock parameter $changedBy di atas -- null waktu
            // dipanggil dari store() (murid baru), non-null waktu
            // dipanggil dari reconciliation update() (jadwal murid yang
            // sudah ada berubah). $batch=true (permintaan user: WA jangan
            // terkirim tiap baris, lihat docblock __construct() di atas)
            // aman selalu true di sini -- cabang ini CUMA pernah tereksekusi
            // dari update(), yang SELALU memanggil flushPengajarNotifications()
            // sendiri setelah semua createRutinFromSlots() untuk request
            // itu selesai.
            if ($changedBy !== null) {
                $this->scheduleChangeNotifier->rutinAdded($rutin, $changedBy, batch: true);
            }
        }

        return [$created, $skipped];
    }

    /**
     * Fix 5 September 2026 (bukti user: Student "Vallery Jocelyn
     * Nathania" -- Bidang tersimpan "Piano", TAPI checklist Edit
     * Student (lihat pengajarSlotsPanel()) menampilkan tab SEMUA
     * Kategori yang pengajar itu ajar LINTAS Bidang -- termasuk
     * Kategori "Jazz" yang sebenarnya anak Bidang "Bass", bukan
     * "Piano". Sebelum perbaikan ini, TIDAK ADA yang mencegah admin
     * mencentang slot di bawah tab "Jazz (Bass)" itu sementara dropdown
     * Bidang di atas form masih terisi "Piano" -- satu kali submit bisa
     * langsung membuat App\Models\JadwalRutin baru yang mismatch
     * (persis gejala yang berulang kali dilaporkan), dan cleanup basi
     * di update() (`$stalePengajarRutins` di atas) TIDAK BISA menangkap
     * ini karena baris itu baru saja dibuat di transaksi YANG SAMA,
     * bukan sisa dari sebelumnya.
     *
     * pengajarSlotsPanel()/create() sekarang MEMFILTER tab checklist ke
     * Bidang yang sedang dipilih (perbaikan #1, mencegah admin bahkan
     * MELIHAT tab yang salah). Method ini adalah lapis kedua (defense
     * in depth) di store()/update(): dipanggil SEBELUM createRutinFromSlots()
     * untuk tiap grup `jadwal_rutin_slot_ids[<kategoriId>]` yang
     * disubmit -- kalau Kategori itu ternyata bukan anak Bidang yang
     * baru saja disimpan di Student ini (mis. request di-tempering,
     * atau race condition user ganti Bidang tapi tab lama sempat
     * ke-submit), grup itu DILEWATI SELURUHNYA (tidak ada JadwalRutin
     * yang dibuat sama sekali untuknya) -- bukan cuma diperingatkan.
     */
    private function kategoriBelongsToMataPelajaran(string $companyId, string $kategoriId, string $mataPelajaranId): bool
    {
        return JadwalKategori::where('company_id', $companyId)
            ->where('id', $kategoriId)
            ->where('jadwal_mata_pelajaran_id', $mataPelajaranId)
            ->exists();
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
     * Semua chunk ketersediaan Pengajar (App\Models\JadwalPengajarJadwal,
     * sudah dipecah per durasi sesi default branch lewat
     * splitSlotIntoChunks()) yang MASIH DALAM JAM OPERASIONAL branch --
     * dipakai checklist di form Add Student & panel Edit Student (lihat
     * class docblock & pengajarSlotsPanel()).
     *
     * Update 4 September 2026 (permintaan user): sebelumnya method ini
     * (dulu bernama openSlotsFor()) MEMBUANG chunk yang bentrok Jadwal
     * Rutin aktif lain (`reject()`), jadi checklist cuma menampilkan
     * yang benar-benar kosong. Sekarang chunk yang bentrok TETAP
     * dikembalikan (ditandai `taken` => true + `taken_by` nama murid
     * yang sudah pakai) -- checklist di blade yang disable-nya (bukan
     * dihapus dari daftar), supaya admin tahu jam itu memang ditawarkan
     * pengajar tapi sudah terisi, bukan mengira jam itu tidak pernah ada
     * sama sekali. `createRutinFromSlots()` (dipanggil saat submit)
     * tetap re-validasi dari nol secara independen -- checkbox yang
     * disabled browser TIDAK PERNAH ikut ter-submit, jadi tidak ada
     * celah keamanan dari perubahan ini.
     *
     * Update 4 September 2026 (bug fix, permintaan user -- "jadwal yg
     * dipilih saat pertamaa kali create tidak keluar ketika ingin
     * melakukan edit"): slot yang sudah jadi Jadwal Rutin AKTIF milik
     * murid yang sama (`$excludeStudentId`) sekarang ditandai `mine` =>
     * true, dipakai `_slot-checklist.blade.php` supaya checkbox-nya
     * otomatis muncul TERCENTANG (sebelumnya cuma dianggap "tidak
     * taken" -- checkbox jadi kosong lagi tiap buka Edit Student,
     * padahal murid itu memang sudah punya jadwal itu dari create()).
     *
     * @param  string|null  $excludeStudentId  Murid yang Jadwal Rutin-nya SENDIRI tidak dihitung "bentrok" (dipakai Edit Student -- murid itu boleh lihat slot yang memang sudah dia pakai sebagai TIDAK taken/`mine` => true, bukan "taken by dirinya sendiri"). null di create() (murid belum ada, `mine` selalu false).
     * @return Collection<int, array{id: string, hari: int, hari_label: string, jam_mulai: string, jam_selesai: string, durasi_menit: int, taken: bool, taken_by: string|null, mine: bool}>
     */
    private function slotsFor($context, JadwalPengajarKategori $pengajarAvailability, string $pengajarId, JadwalBranchSetting $branchSetting, ?string $excludeStudentId = null): Collection
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
            ->map(function (array $chunk) use ($conflictService, $context, $pengajarId, $today, $durasiMenit, $excludeStudentId) {
                $conflict = $conflictService->findPengajarConflict(
                    companyId: $context->company->id,
                    hari: $chunk['hari'],
                    jamMulai: $chunk['jam_mulai'],
                    jamSelesai: $chunk['jam_selesai'],
                    efektifMulai: $today,
                    efektifSelesai: null,
                    pengajarId: $pengajarId,
                );

                $taken = $conflict && $conflict->student_id !== $excludeStudentId;
                $mine = $conflict && $excludeStudentId && $conflict->student_id === $excludeStudentId;

                return [
                    'id' => "{$chunk['slot_id']}|{$chunk['jam_mulai']}",
                    'hari' => $chunk['hari'],
                    'hari_label' => $chunk['hari_label'],
                    'jam_mulai' => $chunk['jam_mulai'],
                    'jam_selesai' => $chunk['jam_selesai'],
                    'durasi_menit' => $durasiMenit,
                    'taken' => $taken,
                    'taken_by' => $taken ? ($conflict->student?->name ?? null) : null,
                    'mine' => $mine,
                ];
            })
            ->values();
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $student = $this->findOrFail($context, $id);

        // TIDAK mengunci field apa pun di sini (selalu dropdown bebas) --
        // locking hanya berlaku di create(), sama seperti pola "ina"
        // project's University Album Photo edit() (lihat class docblock).
        //
        // Update 4 September 2026 (permintaan user, revisi kedua sesi
        // yang sama -- lihat riwayat di pengajarSlotsPanel()): edit()
        // sekarang juga punya CHECKLIST slot ketersediaan Pengajar
        // (sama seperti create(), bukan cuma info read-only lagi) --
        // supaya admin yang mengganti Pengajar murid ini bisa langsung
        // centang & bikin Jadwal Rutin baru dari sini juga, tidak cuma
        // dari alur Tambah Student. Beda dari create() yang scoped ke
        // SATU Kategori (dari konteks drill-down), di sini digabung dari
        // SEMUA Kategori yang Pengajar itu ajar (karena App\Models\
        // JadwalStudent tidak menyimpan Kategori sama sekali) --
        // checklist-nya dikelompokkan per Kategori, lihat
        // jadwal-student/edit.blade.php.
        //
        // HANYA SATU dropdown Pengajar (bukan dua terpisah seperti
        // revisi pertama) -- dropdown Pengajar ASLI di _form.blade.php
        // sendiri yang jadi trigger reload (`onchange` -> GET ulang
        // halaman ini dengan `?pengajar_id=...`, lihat _form.blade.php),
        // MURNI preview (tidak menyimpan apa pun sampai admin klik
        // "Simpan Perubahan"), default ke Pengajar Student ini sekarang.
        $previewPengajarId = $request->query('pengajar_id', $student->pengajar_id);

        // Fix 5 September 2026 (lihat docblock pengajarSlotsPanel()) --
        // Bidang yang sedang di-preview di form (dropdown "Mata
        // Pelajaran / Bidang" di _form.blade.php sekarang juga
        // trigger reload lewat query string, pola sama seperti
        // Pengajar di atas) dipakai untuk MEMFILTER checklist Kategori
        // ke Bidang itu saja -- default ke Bidang tersimpan Student
        // ini kalau belum pernah di-reload sama sekali.
        $previewMataPelajaranId = $request->query('jadwal_mata_pelajaran_id', $student->jadwal_mata_pelajaran_id);

        // Update 4 September 2026 (permintaan user: "tambahkan kolom
        // ruangan pada edit student"): App\Models\JadwalStudent TIDAK
        // menyimpan Ruangan sendiri (persis seperti Kategori, lihat
        // class docblock & kolom "Kategori" di index()) -- Ruangan
        // "sekarang" murid ini DI-DERIVE dari App\Models\JadwalRutin
        // AKTIF milik murid ini (lintas Kategori, karena satu murid
        // dianggap satu Ruangan yang sama untuk SEMUA jadwalnya, lihat
        // docblock update() soal reconciliation-nya). Kalau baris-baris
        // itu KEBETULAN sudah punya Ruangan yang beda-beda (belum pernah
        // diseragamkan lewat fitur ini, atau data lama), TIDAK ada yang
        // di-preselect (biar admin sadar & pilih sendiri) -- ditandai
        // `ruanganMixed` supaya _form.blade.php bisa kasih peringatan.
        $activeRuanganIds = JadwalRutin::where('company_id', $context->company->id)
            ->where('student_id', $student->id)
            ->where('status', JadwalRutin::STATUS_ACTIVE)
            ->whereNotNull('jadwal_ruangan_id')
            ->distinct()
            ->pluck('jadwal_ruangan_id');

        return view('jadwal.jadwal-student.edit', [
            'student' => $student,
            'previewPengajarId' => $previewPengajarId,
            'previewMataPelajaranId' => $previewMataPelajaranId,
            // edit() TIDAK PERNAH mengunci Pengajar (lihat class
            // docblock) -- eksplisit di-set false di sini (bukan cuma
            // mengandalkan default `?? false` di _form.blade.php) supaya
            // jelas ini keputusan sengaja, sama seperti create() yang
            // eksplisit set true/false-nya sendiri.
            'pengajarLocked' => false,
            'selectedRuanganId' => $activeRuanganIds->count() === 1 ? $activeRuanganIds->first() : null,
            'ruanganMixed' => $activeRuanganIds->count() > 1,
        ] + $this->formData($context)
          + $this->pengajarSlotsPanel($context, $previewPengajarId, $student, $previewMataPelajaranId));
    }

    /**
     * Checklist slot ketersediaan Pengajar untuk panel di halaman Edit
     * Student -- lihat docblock edit(). Digabung dari SEMUA
     * App\Models\JadwalPengajarKategori Pengajar itu (satu per Kategori
     * yang dia ajar, beda dari slotsFor() yang scoped ke SATU baris)
     * karena Student tidak menyimpan Kategori. Tiap baris dapat properti
     * tambahan `slots` (hasil slotsFor(), murid ini sendiri dikecualikan
     * dari "taken" lewat `$excludeStudentId` -- lihat docblock
     * slotsFor()) supaya blade tinggal loop per Kategori.
     *
     * Jam Operasional diambil dari branch Student ITU SENDIRI
     * (`$student->branch_office_id`, sudah ada nilainya kalau Student
     * sudah tersimpan) -- BUKAN dari branch Mata Pelajaran tiap Kategori
     * (yang bisa beda-beda kalau Pengajar itu ngajar lintas Mata
     * Pelajaran/branch), supaya satu sumber jam operasional yang
     * konsisten dipakai untuk semua Kategori di panel yang sama.
     *
     * Fix 5 September 2026 (bukti user: "Vallery Jocelyn Nathania" --
     * lihat docblock kategoriBelongsToMataPelajaran()): checklist ini
     * SEBELUMNYA menggabungkan SEMUA Kategori yang Pengajar itu ajar,
     * LINTAS Bidang -- kalau Pengajar mengajar 2 Bidang berbeda (mis.
     * Piano dan Bass), admin yang sedang mengedit Student Bidang
     * "Piano" tetap melihat (dan bisa tercentang) tab Kategori dari
     * Bidang "Bass". Sekarang difilter ke $mataPelajaranId yang sedang
     * dipilih (Bidang tersimpan Student, atau preview dari dropdown --
     * lihat edit()/create() & onchange baru di _form.blade.php) --
     * null berarti belum ada Bidang terpilih sama sekali, checklist
     * kosong (sama seperti belum ada Pengajar terpilih).
     *
     * @return array{pengajarKategoris: \Illuminate\Support\Collection, branchSettingMissing: bool}
     */
    private function pengajarSlotsPanel($context, ?string $pengajarId, JadwalStudent $student, ?string $mataPelajaranId = null): array
    {
        if (! $pengajarId || ! $mataPelajaranId) {
            return ['pengajarKategoris' => collect(), 'branchSettingMissing' => false];
        }

        $branchOfficeId = $student->branch_office_id;
        $branchSetting = $branchOfficeId
            ? JadwalBranchSetting::where('branch_office_id', $branchOfficeId)->first()
            : null;

        $pengajarKategoris = JadwalPengajarKategori::with(['jadwals', 'kategori:id,name'])
            ->where('company_id', $context->company->id)
            ->where('pengajar_id', $pengajarId)
            ->where('status', 'active')
            ->whereHas('kategori', fn ($q) => $q->where('jadwal_mata_pelajaran_id', $mataPelajaranId))
            ->get();

        foreach ($pengajarKategoris as $pk) {
            $pk->setAttribute('slots', $branchSetting
                ? $this->slotsFor($context, $pk, $pengajarId, $branchSetting, $student->id)
                : collect());
            // Update 4 September 2026: per-baris juga (bukan cuma flag
            // global) supaya bentuknya sama dengan create() -- dipakai
            // _kategori-tabs.blade.php yang jadi satu sumber tab dipakai
            // create.blade.php DAN edit.blade.php.
            $pk->setAttribute('branchSettingMissing', ! $branchSetting);
        }

        return [
            'pengajarKategoris' => $pengajarKategoris,
            'branchSettingMissing' => ! $branchSetting,
        ];
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

        // Update 4 September 2026 (permintaan user): checklist slot per
        // Kategori dari panel ketersediaan Pengajar (lihat
        // pengajarSlotsPanel()/edit.blade.php) -- dikirim terkelompok
        // `jadwal_rutin_slot_ids[<jadwal_kategori_id>][]` karena Pengajar
        // di panel itu bisa punya BANYAK Kategori sekaligus (beda dari
        // store() yang cuma satu `jadwal_kategori_id` hidden, scoped ke
        // SATU Kategori dari konteks drill-down). Tiap grup diproses
        // lewat createRutinFromSlots() yang SAMA dipakai store() --
        // re-validasi penuh dari nol per grup (kepemilikan slot,
        // chunk sah, jam operasional, bentrok), bukan cuma dipercaya
        // dari client.
        //
        // Update 4 September 2026 (revisi lagi, permintaan user -- murid
        // mau GANTI jadwal, checklist harus jadi penanda dua arah, bukan
        // cuma nambah): slot `mine` di checklist sekarang BISA di-uncheck
        // (lihat _slot-checklist.blade.php, tidak disabled lagi) --
        // di bawah ini di-reconcile: `pengajarSlotsPanel()` dihitung
        // ULANG dari server (bukan percaya submitted array) untuk tahu
        // slot `mine` mana yang tadinya aktif tapi sekarang tidak ikut
        // ter-submit lagi = admin meng-uncheck-nya. Baris JadwalRutin
        // yang bersangkutan DIHAPUS PERMANEN (bukan dinonaktifkan --
        // keputusan eksplisit user: "jangan di nonaktifkan ... murid itu
        // hanya pengen ganti jadwal nya tapi masih tetap jadi murid",
        // jadi status Student SAMA SEKALI tidak tersentuh, cuma satu
        // baris jadwal mingguannya yang hilang -- sama seperti tombol
        // Hapus di menu Jadwal Rutin). Sesi (JadwalKelas) yang sudah
        // ter-generate bulan ini SENGAJA tidak ikut dihapus (keputusan
        // eksplisit user juga, "biarkan apa adanya") -- FK
        // `jadwal_rutin_id`-nya `nullOnDelete()`, konsisten dengan
        // JadwalRutinController::destroy() yang juga tidak membersihkan
        // sesi.
        $slotIdsByKategori = (array) $request->input('jadwal_rutin_slot_ids', []);
        // Update 4 September 2026 (permintaan user, kolom Ruangan baru
        // di form Edit Student): dropdown Ruangan di sini berlaku untuk
        // SEMUA jadwal aktif murid ini (satu murid = satu Ruangan yang
        // sama lintas Kategori) -- diterapkan ke slot BARU yang dicentang
        // (lewat createRutinFromSlots(), lihat docblock-nya) MAUPUN
        // baris JadwalRutin aktif yang SUDAH ADA dan TETAP dipertahankan
        // (tidak ikut di-uncheck) lewat blok reconciliation di bawah --
        // supaya ganti Ruangan murid tidak perlu uncheck-lalu-centang-
        // ulang semua slotnya satu-satu.
        //
        // Fix 4 September 2026 (laporan user setelah tes: "murid yang
        // sudah mengikuti registrasi dari step awal sampe ada ruangan
        // tapi terbacanya di jadwal kelas tanpa ruangan"): DRAFT
        // PERTAMA fitur ini (komentar lama, SUDAH TIDAK BERLAKU) sengaja
        // TIDAK ikut memindah Ruangan sesi (App\Models\JadwalKelas) yang
        // sudah ter-generate bulan ini -- disamakan begitu saja dengan
        // kebijakan "biarkan apa adanya" untuk perubahan JAM/HARI (lihat
        // revisi checklist di atas). TERNYATA beda kasus: perubahan
        // jam/hari mengubah IDENTITAS jadwal itu sendiri (sesi lama
        // mewakili jadwal yang sudah tidak berlaku lagi, makanya
        // sengaja tidak disentuh), sedangkan Ruangan MURNI metadata di
        // atas sesi yang jam/harinya SAMA PERSIS -- sesi yang sudah ada
        // sebelumnya SELALU `jadwal_ruangan_id = null` (Ruangan belum
        // pernah bisa diisi dari jalur ini sebelum fitur ini ada), jadi
        // tidak ada histori valid yang "ditimpa", cuma data yang belum
        // pernah terisi yang sekarang dilengkapi. Sekarang baris
        // JadwalKelas terkait (`jadwal_rutin_id` yang sama) ikut
        // disamakan Ruangannya -- DIBATASI ke sesi yang `attendance_
        // status`-nya masih kosong (belum diabsen) supaya histori
        // kehadiran yang sudah tercatat tidak diam-diam ditimpa.
        $ruanganId = $validated['jadwal_ruangan_id'] ?? null;

        [$rutinCreated, $rutinSkipped, $rutinRemoved, $ruanganUpdated, $ruanganSkipped, $sesiRuanganUpdated] = DB::transaction(function () use ($context, $company, $validated, $mataPelajaran, $student, $slotIdsByKategori, $ruanganId) {
            $student->update([
                'branch_office_id' => $validated['branch_office_id'] ?? $mataPelajaran?->branch_office_id,
                'jadwal_mata_pelajaran_id' => $validated['jadwal_mata_pelajaran_id'],
                'pengajar_id' => $validated['pengajar_id'],
                'name' => $validated['name'],
                'parent_phone_number' => $validated['parent_phone_number'] ?? null,
                'student_phone_number' => $validated['student_phone_number'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            $created = 0;
            $skipped = [];
            $removed = 0;

            // Fix 4 September 2026 (laporan user: "di menu pengajar,
            // tidak ada muridnya tapi ketika saya buka di menu student
            // pengajarnya sudah keluar ... di jadwal kelas itu bidangnya
            // bass, category nya jazz tapi di student itu piano") --
            // BUG ditemukan: reconciliation checklist di bawah ($panel,
            // loop $toRemove) HANYA pernah melihat baris App\Models\
            // JadwalRutin yang pengajar_id-nya SAMA dengan pengajar yang
            // BARU dipilih ($validated['pengajar_id']) -- kalau admin
            // GANTI Pengajar murid ini (dropdown Pengajar paling atas di
            // form ini, bukan checklist-nya), baris JadwalRutin AKTIF
            // yang masih menunjuk ke pengajar LAMA sama sekali tidak
            // pernah ke-query, jadi tidak pernah dinonaktifkan -- tetap
            // `status = active` selamanya, dan App\Console\Commands\
            // GenerateJadwalRutinSesi (jalan tiap bulan) TERUS membuat
            // App\Models\JadwalKelas baru dari baris itu, membawa
            // snapshot Pengajar/Bidang/Kategori LAMA (mis. "Bass/Jazz")
            // walau App\Models\JadwalStudent murid ini sendiri sudah
            // bilang Pengajar/Mata Pelajaran BARU (mis. "Piano") -- akar
            // dari SEMUA gejala yang dilaporkan (menu Pengajar menghitung
            // dari kombinasi pengajar+mapel yang valid jadi kosong, menu
            // Student baca field mentah jadi tetap muncul, Jadwal Kelas
            // terus ke-generate dari data basi).
            //
            // Satu baris App\Models\JadwalStudent didesain represent SATU
            // hubungan murid-Pengajar (drill-down Branch -> Mata
            // Pelajaran -> PENGAJAR -> Student, field pengajar_id di
            // level Student sendiri) -- jadi TIDAK ADA skenario sah baris
            // JadwalRutin AKTIF murid ini menunjuk ke Pengajar LAIN dari
            // pengajar_id yang sekarang tersimpan di Student-nya sendiri.
            // Baris begini SELALU sisa dari pergantian Pengajar
            // sebelumnya yang tidak ke-reconcile -- aman dibersihkan di
            // sini, pola PERSIS sama dengan loop $toRemove di bawah
            // (rutinRemoved() dulu sebelum dihapus: nonaktifkan sesi masa
            // depan yang belum diabsen, tulis JadwalChangeLog, WA
            // konsolidasi ke pengajar lama -- lihat docblock
            // JadwalScheduleChangeNotifier::rutinRemoved()). Sesi
            // (JadwalKelas) yang SUDAH ter-generate dari baris ini tetap
            // TIDAK disentuh (kebijakan sama seperti reconciliation
            // checklist di bawah) -- histori/fee yang sudah tercatat
            // tidak diam-diam berubah.
            //
            // Fix susulan 5 September 2026 (bukti user: murid "Vallery
            // Jocelyn Nathania" -- Student menyimpan Mata Pelajaran/
            // Bidang = "Piano" + Pengajar = "Stevany N", TAPI kolom
            // Kategori di index (di-derive dari JadwalRutin AKTIF, lihat
            // index() di atas) tetap menunjukkan "Jazz" -- yang secara
            // relasi ada di BAWAH Bidang "Bass", bukan "Piano". Root
            // cause: kondisi di atas cuma bereaksi kalau PENGAJAR
            // berubah -- kalau admin ganti BIDANG/Mata Pelajaran murid
            // (Bass -> Piano) sementara Pengajar-nya TETAP SAMA (Stevany
            // N kebetulan mengajar keduanya), baris JadwalRutin lama di
            // bawah Kategori Bass/Jazz tidak pernah ke-match kondisi
            // pengajar_id di atas, jadi tidak pernah ikut dibersihkan --
            // tetap aktif, terus di-generate GenerateJadwalRutinSesi
            // setiap bulan, dan Kategori "Jazz" (milik Bidang Bass) terus
            // nempel di index Student yang Bidang-nya sudah "Piano".
            // Diperlebar di sini: baris JadwalRutin AKTIF murid ini juga
            // dianggap basi (ikut dibersihkan) kalau Kategori-nya
            // (lewat jadwal_kategori.jadwal_mata_pelajaran_id) sudah
            // tidak cocok dengan Mata Pelajaran/Bidang yang BARU
            // tersimpan di Student ini -- terlepas dari Pengajar-nya
            // sama atau tidak. Kebijakan sisanya (rutinRemoved() dulu,
            // sesi JadwalKelas historis tidak disentuh) tetap identik.
            $stalePengajarRutins = JadwalRutin::where('company_id', $company->id)
                ->where('student_id', $student->id)
                ->where('status', JadwalRutin::STATUS_ACTIVE)
                ->where(function ($q) use ($validated) {
                    $q->where('pengajar_id', '!=', $validated['pengajar_id'])
                        ->orWhereHas('kategori', function ($k) use ($validated) {
                            $k->where('jadwal_mata_pelajaran_id', '!=', $validated['jadwal_mata_pelajaran_id']);
                        });
                })
                ->get();

            foreach ($stalePengajarRutins as $row) {
                $this->scheduleChangeNotifier->rutinRemoved($row, auth()->id(), batch: true);
                $row->delete();
                $removed++;
            }

            $panel = $this->pengajarSlotsPanel($context, $validated['pengajar_id'], $student);

            foreach ($panel['pengajarKategoris'] as $pk) {
                $submitted = array_values(array_filter((array) ($slotIdsByKategori[$pk->jadwal_kategori_id] ?? [])));

                $toRemove = $pk->slots
                    ->where('mine', true)
                    ->reject(fn (array $slot) => in_array($slot['id'], $submitted, true));

                foreach ($toRemove as $slot) {
                    // Update 4 September 2026 (laporan user: "jadwal
                    // student bisa ke-add 2x/3x .. wa nya kadang
                    // terkirim kadang tidak") -- SEBELUMNYA langsung
                    // ->delete() TANPA mengambil barisnya dulu, jadi
                    // sesi (JadwalKelas) yang sudah ter-generate dari
                    // baris JadwalRutin ini dibiarkan begitu saja
                    // (status tetap 'active' walau jamnya sudah tidak
                    // berlaku -- itu "sesi hantu" yang tetap masuk
                    // antrian jadwal:dispatch-due-reminders). Sekarang
                    // diambil DULU (bisa lebih dari satu baris kalau ada
                    // data ganda), tiap baris lewat
                    // JadwalScheduleChangeNotifier::rutinRemoved() SEBELUM
                    // dihapus -- lihat docblock class itu untuk detail
                    // lengkap (nonaktifkan sesi masa depan yang belum
                    // diabsen, tulis App\Models\JadwalChangeLog, WA ke
                    // pengajar lama).
                    $rows = JadwalRutin::where('company_id', $company->id)
                        ->where('student_id', $student->id)
                        ->where('jadwal_kategori_id', $pk->jadwal_kategori_id)
                        ->where('pengajar_id', $validated['pengajar_id'])
                        ->where('status', JadwalRutin::STATUS_ACTIVE)
                        ->where('hari', $slot['hari'])
                        ->where('jam_mulai', $slot['jam_mulai'])
                        ->get();

                    foreach ($rows as $row) {
                        // $batch=true -- lihat docblock __construct() di
                        // atas & flushPengajarNotifications() (permintaan
                        // user: WA jangan terkirim tiap baris yang
                        // berubah, digabung jadi satu per Pengajar).
                        // Ditampung dulu, dikirim SEKALIGUS di akhir
                        // closure transaksi ini (lihat pemanggilan
                        // flushPengajarNotifications() di bawah).
                        $this->scheduleChangeNotifier->rutinRemoved($row, auth()->id(), batch: true);
                        $row->delete();
                        $removed++;
                    }
                }
            }

            foreach ($slotIdsByKategori as $kategoriId => $chunkIds) {
                $chunkIds = array_values(array_filter((array) $chunkIds));

                if (! $kategoriId || ! $chunkIds) {
                    continue;
                }

                // Fix 5 September 2026 -- lihat docblock
                // kategoriBelongsToMataPelajaran(): jangan pernah bikin
                // JadwalRutin dari Kategori yang bukan anak Bidang yang
                // baru saja dipilih di Student ini (mis. tab checklist
                // Kategori dari Bidang lain sempat ke-submit).
                if (! $this->kategoriBelongsToMataPelajaran($company->id, (string) $kategoriId, $validated['jadwal_mata_pelajaran_id'])) {
                    $skipped[] = 'Slot di bawah Kategori yang bukan bagian dari Mata Pelajaran / Bidang yang dipilih dilewati (tidak disimpan).';

                    continue;
                }

                [$c, $s] = $this->createRutinFromSlots($company, $student, (string) $kategoriId, $validated['pengajar_id'], $chunkIds, $ruanganId, auth()->id());
                $created += $c;
                $skipped = array_merge($skipped, $s);
            }

            // Reconciliation Ruangan (lihat komentar panjang di atas) --
            // baris JadwalRutin aktif yang TETAP dipertahankan (bukan
            // baru dibuat, bukan di-uncheck di atas) disamakan Ruangan-
            // nya ke pilihan dropdown saat ini. Dicek ULANG bentrok
            // Ruangan per baris (`ignoreId` supaya baris itu sendiri
            // tidak dianggap bentrok dengan dirinya sendiri) -- kalau
            // Ruangan barunya ternyata sudah dipakai murid LAIN di jam
            // yang sama, baris itu dilewati (Ruangan lamanya tetap,
            // dilaporkan lewat `$ruanganSkipped`) bukan dipaksa pindah.
            $ruanganUpdatedCount = 0;
            $sesiRuanganUpdatedCount = 0;
            $ruanganSkippedMsgs = [];
            $conflictService = app(JadwalRutinConflictService::class);

            // Ambil SEMUA baris aktif murid+pengajar ini (jumlahnya kecil,
            // wajar di-loop di PHP -- lebih gampang dibaca daripada WHERE
            // gabungan buat "beda dari $ruanganId, termasuk kalau
            // $ruanganId null" yang berantakan kalau ditulis di query).
            $stillActive = JadwalRutin::where('company_id', $company->id)
                ->where('student_id', $student->id)
                ->where('pengajar_id', $validated['pengajar_id'])
                ->where('status', JadwalRutin::STATUS_ACTIVE)
                ->get();

            foreach ($stillActive as $row) {
                if ($row->jadwal_ruangan_id === $ruanganId) {
                    continue;
                }

                if ($ruanganId) {
                    $ruanganConflict = $conflictService->findRuanganConflict(
                        companyId: $company->id,
                        hari: $row->hari,
                        jamMulai: substr($row->jam_mulai, 0, 5),
                        jamSelesai: $row->jamSelesai(),
                        efektifMulai: $row->efektif_mulai?->toDateString() ?? now()->toDateString(),
                        efektifSelesai: $row->efektif_selesai?->toDateString(),
                        jadwalRuanganId: $ruanganId,
                        ignoreId: $row->id,
                    );

                    if ($ruanganConflict && $ruanganConflict->student_id !== $student->id) {
                        $ruanganSkippedMsgs[] = $row->hariLabel().' '.substr($row->jam_mulai, 0, 5)."-{$row->jamSelesai()} (Ruangan baru sudah dipakai murid lain: ".($ruanganConflict->student?->name ?? '-').')';

                        continue;
                    }
                }

                $row->update(['jadwal_ruangan_id' => $ruanganId]);
                $ruanganUpdatedCount++;

                // Fix 4 September 2026 (lihat komentar panjang di atas
                // soal laporan user) -- ikut samakan Ruangan sesi
                // (App\Models\JadwalKelas) yang SUDAH ter-generate dari
                // baris Jadwal Rutin ini, supaya "Jadwal Kelas" (halaman
                // yang terus dipantau admin) langsung akurat tanpa
                // nunggu generate ulang bulan depan. Dibatasi ke sesi
                // yang BELUM diabsen (`attendance_status` masih kosong)
                // -- sesi yang sudah tercatat kehadirannya dianggap
                // histori, tidak diam-diam ditimpa Ruangan barunya.
                $sesiRuanganUpdatedCount += JadwalKelas::where('company_id', $company->id)
                    ->where('jadwal_rutin_id', $row->id)
                    ->whereNull('attendance_status')
                    ->update(['jadwal_ruangan_id' => $ruanganId]);
            }

            // Kirim SEMUA WA yang ditampung rutinRemoved()/rutinAdded()
            // (batch: true) di atas SEKALIGUS di sini -- SATU pesan per
            // Pengajar untuk SELURUH perubahan submit ini (bukan satu
            // pesan per baris JadwalRutin), permintaan user: "ketika ada
            // perubahan jadwal wa nya terkirim 1x saja ya tidak tiap ada
            // perubahan ya". Lihat docblock __construct() &
            // JadwalScheduleChangeNotifier::flushPengajarNotifications().
            $this->scheduleChangeNotifier->flushPengajarNotifications();

            return [$created, $skipped, $removed, $ruanganUpdatedCount, $ruanganSkippedMsgs, $sesiRuanganUpdatedCount];
        });

        $message = 'Student berhasil diperbarui.';

        if ($rutinCreated > 0) {
            $message .= " {$rutinCreated} Jadwal Rutin baru dibuat dari slot yang dicentang, sesi bulan ini langsung digenerate.";
        }

        if ($ruanganUpdated > 0) {
            $message .= " Ruangan diperbarui untuk {$ruanganUpdated} jadwal murid ini";
            $message .= $sesiRuanganUpdated > 0
                ? " ({$sesiRuanganUpdated} sesi Jadwal Kelas yang belum diabsen ikut disamakan Ruangannya)."
                : '.';
        }

        if (! empty($ruanganSkipped)) {
            $message .= ' Ruangan tidak bisa diubah untuk: '.implode('; ', $ruanganSkipped).'.';
        }

        if ($rutinRemoved > 0) {
            $message .= " {$rutinRemoved} Jadwal Rutin dihapus dari slot yang di-uncheck.";
        }

        if (! empty($rutinSkipped)) {
            $message .= ' Slot yang dilewati: '.implode('; ', $rutinSkipped).'.';
        }

        return redirect()
            ->route('jadwal.student.index', [
                'jadwal_mata_pelajaran_id' => $student->jadwal_mata_pelajaran_id,
                'pengajar_id' => $student->pengajar_id,
            ])
            ->with('success', $message);
    }

    /**
     * "Nonaktifkan" -- permintaan user (4 September 2026, sesudah
     * ditemukan bug tombol Hapus lama selalu gagal, lihat docblock
     * destroy()): alternatif AMAN dari Hapus Total, dipakai kalau murid
     * ini masih ingin datanya disimpan (histori sesi & fee) tapi tidak
     * lagi aktif. TIDAK menghapus apa pun -- hanya:
     *
     * 1. Set `status` = inactive di baris Student ini sendiri.
     * 2. Semua App\Models\JadwalRutin AKTIF murid ini ikut di-set
     *    inactive (BUKAN dihapus) -- supaya tidak lagi diambil
     *    App\Console\Commands\GenerateJadwalRutinSesi bulan depan
     *    (generator itu filter `status = active`).
     * 3. Tiap baris JadwalRutin itu diproses lewat
     *    App\Services\Jadwal\JadwalScheduleChangeNotifier::rutinRemoved()
     *    SEBELUM di-set inactive -- method yang SAMA dipakai waktu
     *    admin uncheck slot di form Edit Student (lihat update()) --
     *    supaya sesi masa depan yang belum diabsen ikut dinonaktifkan
     *    (tidak lagi masuk antrian pengingat WA), histori before/after
     *    ikut tercatat di jadwal_change_logs, DAN pengajar dapat kabar
     *    WA jadwalnya sudah tidak berlaku.
     *
     * Permintaan user juga: sesi LAMA murid ini (yang attendance-nya
     * sudah tercatat) TIDAK ikut hilang dari database, TAPI tidak lagi
     * ikut dihitung di laporan fee/komisi manapun begitu murid ini
     * inactive -- lihat filter `whereHas('student', ...STATUS_ACTIVE)`
     * di App\Services\Jadwal\JadwalLaporanService::rekap().
     */
    public function deactivate(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $student = $this->findOrFail($context, $id);

        $mataPelajaranId = $student->jadwal_mata_pelajaran_id;
        $pengajarId = $student->pengajar_id;

        DB::transaction(function () use ($student, $request) {
            $activeRutins = JadwalRutin::where('company_id', $student->company_id)
                ->where('student_id', $student->id)
                ->where('status', JadwalRutin::STATUS_ACTIVE)
                ->get();

            foreach ($activeRutins as $rutin) {
                // $batch=true + flush sekali di akhir -- sama alasannya
                // dengan update() (lihat docblock __construct()): murid
                // yang punya beberapa Jadwal Rutin sekaligus (mis. lebih
                // dari satu Kategori/hari) sebelumnya bikin Pengajar yang
                // sama dapat WA terpisah per baris waktu dinonaktifkan.
                $this->scheduleChangeNotifier->rutinRemoved($rutin, $request->user()?->id, batch: true);
                $rutin->update(['status' => JadwalRutin::STATUS_INACTIVE]);
            }

            $this->scheduleChangeNotifier->flushPengajarNotifications();

            $student->update(['status' => JadwalStudent::STATUS_INACTIVE]);
        });

        return redirect()
            ->route('jadwal.student.index', [
                'jadwal_mata_pelajaran_id' => $mataPelajaranId,
                'pengajar_id' => $pengajarId,
            ])
            ->with('success', 'Student berhasil dinonaktifkan. Riwayat jadwal & fee-nya tetap tersimpan, tapi tidak lagi ikut dihitung di laporan.');
    }

    /**
     * "Hapus Total" -- permintaan user (4 September 2026, laporan
     * "fungsi delete di table student tidak berfungsi"): SEBELUMNYA
     * method ini SELALU gagal (error mentah, tidak ada try/catch) kalau
     * murid sudah punya sesi (App\Models\JadwalKelas), karena FK
     * `jadwal_kelas.student_id` masih `restrictOnDelete()` -- lihat
     * migration 2026_09_14_090800_change_jadwal_kelas_student_id_to_
     * cascade_on_delete_table.php yang mengubahnya jadi
     * `cascadeOnDelete()`.
     *
     * User memutuskan aksi ini memang untuk hapus PERMANEN seluruh
     * rangkaian data murid: JadwalStudent -> JadwalRutin (sudah
     * cascadeOnDelete sejak awal) -> JadwalKelas (baru diubah) beserta
     * histori fee/komisi yang nempel di tiap barisnya -- "biar ga
     * bingung murid sudah dihapus tapi datanya tetap seperti itu".
     * TIDAK BISA DIBATALKAN. Untuk murid yang datanya masih ingin
     * disimpan, pakai deactivate() di atas.
     */
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
            ->with('success', 'Student beserta seluruh riwayat jadwal & fee-nya berhasil dihapus permanen.');
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
            // Update 4 September 2026 (permintaan user: "tambahkan kolom
            // ruangan pada edit student ... dan add student juga") --
            // dropdown Ruangan baru di _form.blade.php, di-scope ke
            // branch yang sama seperti branchOffices/teamMembers di atas
            // (kalau company di-lock ke satu branch, cuma Ruangan branch
            // itu; kalau tidak, semua Ruangan aktif company -- sama
            // pola dengan mataPelajarans yang juga tidak difilter branch
            // di skenario bebas). `branchOffice:id,name` di-eager-load
            // supaya label dropdown bisa menyertakan nama branch kalau
            // company punya lebih dari satu (lihat _form.blade.php).
            'ruangans' => JadwalRuangan::where('company_id', $context->company->id)
                ->where('status', JadwalRuangan::STATUS_ACTIVE)
                ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
                ->with('branchOffice:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'branch_office_id']),
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
        $validator = Validator::make($request->all(), [
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
            // Update 4 September 2026 (permintaan user, kolom Ruangan
            // baru): sama pola company-scoped check seperti branch_
            // office_id di atas -- lihat App\Http\Controllers\Jadwal\
            // JadwalRutinController::validator() untuk aturan yang
            // persis sama dipakai alur manual Jadwal Rutin.
            'jadwal_ruangan_id' => [
                'nullable', 'uuid', 'exists:jadwal_ruangan,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalRuangan::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Ruangan tidak valid.');
                    }
                },
            ],
        ]);

        // Fix 4 September 2026 (laporan user: "di menu pengajar, tidak
        // ada muridnya tapi ketika saya buka di menu student pengajarnya
        // sudah keluar") -- SEBELUMNYA pengajar_id di atas cuma dicek
        // "user beneran ada" (exists:users,id), TIDAK PERNAH dicek
        // pengajar itu SUNGGUHAN ditugaskan mengajar Mata Pelajaran /
        // Bidang yang dipilih (App\Models\JadwalPengajarKategori, status
        // aktif) -- dropdown Pengajar di form ini bebas (companyPengajar
        // Members(), semua anggota tim, TIDAK difilter per Mata
        // Pelajaran), jadi admin bisa (tanpa sengaja) menyimpan
        // kombinasi Pengajar+Mata-Pelajaran yang tidak nyata. Akibatnya:
        // App\Http\Controllers\Jadwal\JadwalPengajarController::index()
        // menghitung murid dari kombinasi pengajar+mapel yang VALID
        // (lihat attachMuridCounts()) jadi murid itu tidak ke-hitung di
        // sana (tampil 0), padahal App\Http\Controllers\Jadwal\
        // JadwalStudentController::index() (menu Student) baca langsung
        // dari field mentah JadwalStudent tanpa validasi serupa, jadi
        // tetap muncul -- dua menu "benar" menurut query masing-masing,
        // tapi datanya sendiri sudah tidak valid sejak disimpan.
        //
        // $validator->after() dipakai (bukan rule per-field) karena
        // butuh 2 field sekaligus (pengajar_id + jadwal_mata_pelajaran_id).
        // Dilewati (tidak divalidasi) kalau salah satu masih kosong --
        // rule 'required' masing-masing di atas yang menangani itu.
        $validator->after(function ($v) use ($request, $company) {
            $pengajarId = $request->input('pengajar_id');
            $mataPelajaranId = $request->input('jadwal_mata_pelajaran_id');

            if (! $pengajarId || ! $mataPelajaranId) {
                return;
            }

            $valid = JadwalPengajarKategori::where('company_id', $company->id)
                ->where('pengajar_id', $pengajarId)
                ->where('status', JadwalPengajarKategori::STATUS_ACTIVE)
                ->whereHas('kategori', fn ($q) => $q->where('jadwal_mata_pelajaran_id', $mataPelajaranId))
                ->exists();

            if (! $valid) {
                $v->errors()->add('pengajar_id', 'Pengajar ini belum ditugaskan mengajar Mata Pelajaran / Bidang yang dipilih -- tugaskan dulu lewat menu Pengajar, atau pilih Pengajar/Mata Pelajaran lain.');
            }
        });

        return $validator;
    }
}
