<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\CompanyToUser;
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
use Illuminate\Support\Collection;
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
 * index() TIDAK LAGI tabel flat paginated -- diganti total jadi
 * tab per Ruangan (+ 1 tab "Tanpa Ruangan" untuk sesi yang belum diisi
 * ruangannya) x grid slot waktu 30 menit TETAP per hari (lihat
 * buildSlotGrid()), meniru mockup admin: baris = slot waktu, kolom =
 * Pengajar/Bidang/Kategori/Murid/Status/Aksi, sel kosong = slot belum
 * ada sesi. Jam Buka/Tutup grid diambil dari App\Models\
 * JadwalBranchSetting milik branch Ruangan yang aktif. Sesi yang
 * start_time-nya null (tidak bisa ditaruh di grid manapun) ditampilkan
 * flat terpisah per tab supaya tidak pernah hilang dari tampilan.
 *
 * Update 7 September 2026 (permintaan user, "papan jadwal vs jam
 * operasional"): index() sekarang punya 2 MODE pengelompokan, dipilih
 * lewat query `group_by` ('ruangan', default, ATAU 'pengajar') --
 * grid slot 30-menit & buildSlotGrid() dipakai bersama oleh keduanya
 * (sumber data + cara pecah jam operasionalnya identik), yang beda
 * cuma sumbu tab-nya: mode 'ruangan' = tab per App\Models\JadwalRuangan
 * (seperti sebelumnya, kolom "Pengajar" jadi info per baris), mode
 * 'pengajar' = tab per Team Member ber-role is_pengajar (lihat
 * ResolvesCompanyContext::companyPengajarMembers()), kolom "Ruangan"
 * jadi info per baris (kolom Pengajar dilepas, karena sudah jadi
 * konteks tab). Kedua mode berbagi 1 partial tabel
 * (jadwal-kelas/_slot-grid-table.blade.php) supaya markup baris
 * (dropdown kehadiran, badge sesi pengganti, tombol aksi) tidak
 * dobel. Jam Operasional grid mode 'pengajar' diambil dari branch
 * Team Member itu sendiri (App\Models\CompanyToUser::branch_office_id,
 * fallback ke filter `branch_office_id` di URL kalau ada) -- BUKAN
 * dari Ruangan (pengajar tidak terikat 1 ruangan tetap). `group_by`
 * (+ `pengajar_id`/`ruangan_id` yang relevan) dibawa lewat query
 * string/hidden input ke create()/edit()/store()/update()/destroy()
 * supaya admin balik ke tab yang sama setelah simpan/hapus -- lihat
 * gridRedirectParams().
 *
 * create() mengunci field locked-vs-free seperti sebelumnya (branch,
 * mata pelajaran, pengajar, student kalau datang lengkap dari index
 * Student) DITAMBAH ruangan (+ prefill tanggal/jam mulai) kalau datang
 * dari klik slot kosong di grid index() -- lihat class docblock lama
 * & JadwalKelasController::create()'s inline docblock untuk detail.
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

        // Mode pengelompokan grid -- lihat class docblock, update 7
        // September 2026. Nilai lain/tidak dikenal jatuh ke 'ruangan'
        // (default lama, supaya link/bookmark tanpa `group_by` tetap
        // jalan seperti sebelumnya).
        $groupBy = $request->query('group_by') === 'pengajar' ? 'pengajar' : 'ruangan';

        $ruangans = JadwalRuangan::where('company_id', $company->id)
            ->where('status', JadwalRuangan::STATUS_ACTIVE)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->with('branchOffice:id,name')
            ->orderBy('name')
            ->get();

        // Daftar Pengajar (mode 'pengajar') -- lihat
        // ResolvesCompanyContext::companyPengajarMembers(), sudah
        // dipakai dropdown Pengajar di formData() bawah, dipakai lagi
        // di sini sebagai daftar tab.
        $pengajars = $this->companyPengajarMembers($company, $branchOfficeId);

        $date = $request->filled('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : now()->startOfDay();

        $baseQuery = JadwalKelas::where('company_id', $company->id)
            ->with([
                'pengajar:id,name', 'student:id,name', 'mataPelajaran:id,name', 'kategori:id,name',
                'ruangan:id,name',
                'sesiPengganti:id,pengganti_dari_sesi_id,start_time',
                'penggantiDariSesi:id,start_time',
            ]);

        if ($context->isLockedToBranch()) {
            $baseQuery->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        $slotRows = [];
        $branchSetting = null;
        $noTimeInRoom = collect();
        $noRuanganList = null;
        $activeRuanganId = null;
        $activeRuangan = null;
        $noTimeForPengajar = collect();
        $activePengajarId = null;
        $activePengajar = null;

        if ($groupBy === 'pengajar') {
            // Default ke Pengajar pertama; kalau id yang diminta tidak
            // ketemu (link basi / sudah tidak eligible lagi), tetap
            // jatuh ke Pengajar pertama daripada nampilin grid kosong.
            $requestedPengajarId = $request->query('pengajar_id');
            $activePengajar = $requestedPengajarId ? $pengajars->firstWhere('id', $requestedPengajarId) : null;
            $activePengajar = $activePengajar ?: $pengajars->first();
            $activePengajarId = $activePengajar?->id;

            if ($activePengajar) {
                // Jam Operasional grid ikut BRANCH si Pengajar (bukan
                // Ruangan -- pengajar tidak terikat 1 ruangan tetap),
                // lihat class docblock. Fallback ke filter
                // `branch_office_id` URL (kalau ada) dulu sebelum ke
                // branch resmi Team Member itu di App\Models\CompanyToUser.
                $pengajarBranchOfficeId = $branchOfficeId
                    ?: CompanyToUser::where('company_id', $company->id)
                        ->where('user_id', $activePengajar->id)
                        ->whereNotNull('branch_office_id')
                        ->value('branch_office_id');

                $branchSetting = $pengajarBranchOfficeId
                    ? BranchOffice::find($pengajarBranchOfficeId)?->jadwalBranchSetting
                    : null;

                $kelasHariIni = (clone $baseQuery)
                    ->where('pengajar_id', $activePengajar->id)
                    ->whereDate('start_time', $date->toDateString())
                    ->orderBy('start_time')
                    ->get();

                $slotRows = $this->buildSlotGrid($kelasHariIni, $branchSetting, $date);

                // Sama seperti $noTimeInRoom di mode 'ruangan' -- sesi
                // pengajar ini yang belum punya start_time sama sekali,
                // tidak terikat tanggal yang dipilih.
                $noTimeForPengajar = (clone $baseQuery)
                    ->where('pengajar_id', $activePengajar->id)
                    ->whereNull('start_time')
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();
            }
        } else {
            // 'none' = tab "Tanpa Ruangan" -- sesi yang jadwal_ruangan_id-nya
            // masih kosong (manual, belum ditentukan ruangannya -- ruangan
            // SELALU opsional, lihat docblock App\Models\JadwalRuangan &
            // App\Models\JadwalRutin). Default ke ruangan pertama; kalau
            // belum ada satu pun Ruangan terdaftar di branch ini, otomatis
            // jatuh ke tab 'none' supaya halaman tidak kosong-melompong.
            $activeRuanganId = $request->query('ruangan_id') ?: ($ruangans->first()?->id ?? 'none');
            $activeRuangan = $activeRuanganId !== 'none' ? $ruangans->firstWhere('id', $activeRuanganId) : null;

            if ($activeRuangan) {
                $branchSetting = $activeRuangan->branchOffice?->jadwalBranchSetting;

                $kelasHariIni = (clone $baseQuery)
                    ->where('jadwal_ruangan_id', $activeRuangan->id)
                    ->whereDate('start_time', $date->toDateString())
                    ->orderBy('start_time')
                    ->get();

                $slotRows = $this->buildSlotGrid($kelasHariIni, $branchSetting, $date);

                // Sesi di ruangan ini yang belum punya start_time sama
                // sekali -- tidak bisa masuk grid slot manapun (grid butuh
                // tanggal+jam), jadi ditampilkan flat di sini supaya admin
                // tetap bisa mengelolanya (kasih waktu / hapus), bukan
                // hilang diam-diam. Tidak terikat tanggal yang dipilih.
                $noTimeInRoom = (clone $baseQuery)
                    ->where('jadwal_ruangan_id', $activeRuangan->id)
                    ->whereNull('start_time')
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();
            } else {
                // Tab "Tanpa Ruangan" -- semua sesi yang belum diisi
                // ruangannya sama sekali, lintas tanggal (tidak ada grid
                // per-hari yang masuk akal tanpa satu Ruangan spesifik).
                $noRuanganList = (clone $baseQuery)
                    ->whereNull('jadwal_ruangan_id')
                    ->orderByRaw('start_time IS NULL, start_time DESC')
                    ->paginate(20)
                    ->withQueryString()
                    ->onEachSide(1);
            }
        }

        return view('jadwal.jadwal-kelas.index', [
            'groupBy' => $groupBy,
            'ruangans' => $ruangans,
            'activeRuanganId' => $activeRuanganId,
            'activeRuangan' => $activeRuangan,
            'pengajars' => $pengajars,
            'activePengajarId' => $activePengajarId,
            'activePengajar' => $activePengajar,
            'branchSetting' => $branchSetting,
            'date' => $date,
            'slotRows' => $slotRows,
            'noTimeInRoom' => $noTimeInRoom,
            'noTimeForPengajar' => $noTimeForPengajar,
            'noRuanganList' => $noRuanganList,
            'branchOfficeId' => $branchOfficeId,
        ]);
    }

    /**
     * Bangun grid slot waktu 30 menit TETAP (spec: interval selalu 30
     * menit apapun durasi sesi aslinya -- sesi 60 menit menempati 2
     * baris slot lewat rowspan, sama pola Excel-merge yang sebelumnya
     * dipakai index() versi flat) dari jam_buka s/d jam_tutup
     * $branchSetting. Tanpa $branchSetting (branch belum diisi Jam
     * Operasional), fallback 08:00-20:00 supaya halaman tetap bisa
     * dipakai (bukan blank/error) -- lihat noBranchSetting flag di
     * view untuk peringatannya ke admin.
     *
     * @return array<int, array{time: string, kelas: ?JadwalKelas, rowspan: int, isBreak: bool}>
     */
    private function buildSlotGrid(Collection $kelasHariIni, ?JadwalBranchSetting $branchSetting, Carbon $date): array
    {
        $slotMinutes = 30;
        $jamBuka = $branchSetting->jam_buka ?? '08:00:00';
        $jamTutup = $branchSetting->jam_tutup ?? '20:00:00';
        // Ambil 5 karakter pertama ("H:i") -- kolom DB TIME bisa balik
        // sebagai "H:i:s", sementara $key di bawah selalu "H:i" (format
        // Carbon), supaya perbandingan string konsisten & tidak salah
        // (mis. "12:00" vs "12:00:00" beda secara string walau sama
        // secara waktu).
        $istirahatMulai = $branchSetting?->jam_istirahat_mulai ? substr($branchSetting->jam_istirahat_mulai, 0, 5) : null;
        $istirahatSelesai = $branchSetting?->jam_istirahat_selesai ? substr($branchSetting->jam_istirahat_selesai, 0, 5) : null;

        // SELALU pakai $date yang diminta (bukan tanggal sesi pertama) --
        // kalau hari itu kosong sama sekali (belum ada sesi), grid tetap
        // harus tampil untuk tanggal yang benar, bukan diam-diam jatuh
        // ke hari ini.
        $cursor = $date->copy()->setTimeFromTimeString($jamBuka);
        $end = $date->copy()->setTimeFromTimeString($jamTutup);

        // Index slot berdasar waktu mulai (format "H:i") -- dipakai
        // untuk nempelin sesi ke slot yang tepat & hitung rowspan-nya.
        $occupiedBy = []; // 'H:i' => JadwalKelas
        $spanOf = [];     // 'H:i' => int slot yang dipakai
        $covered = [];    // 'H:i' => true (bagian tengah rowspan, di-skip)
        $outsideGrid = [];

        foreach ($kelasHariIni as $kelas) {
            if (! $kelas->start_time) {
                continue;
            }

            $startKey = $kelas->start_time->format('H:i');

            if ($kelas->start_time->lt($cursor) || $kelas->start_time->gte($end)) {
                $outsideGrid[] = $kelas;

                continue;
            }

            $durationMinutes = $kelas->end_time
                ? $kelas->start_time->diffInMinutes($kelas->end_time)
                : $slotMinutes;
            $span = max(1, (int) ceil($durationMinutes / $slotMinutes));

            $occupiedBy[$startKey] = $kelas;
            $spanOf[$startKey] = $span;

            // Tandai slot-slot berikutnya yang "ketutup" rowspan sesi
            // ini supaya tidak dirender baris baru (sama logika rowspan
            // Excel-merge index() versi lama).
            $slotCursor = $cursor->copy()->setTimeFromTimeString($startKey.':00');

            for ($i = 1; $i < $span; $i++) {
                $slotCursor->addMinutes($slotMinutes);

                if ($slotCursor->gte($end)) {
                    break;
                }

                $covered[$slotCursor->format('H:i')] = true;
            }
        }

        $rows = [];

        while ($cursor->lt($end)) {
            $key = $cursor->format('H:i');

            if (! isset($covered[$key])) {
                $isBreak = $istirahatMulai && $istirahatSelesai && $key >= $istirahatMulai && $key < $istirahatSelesai;

                $rows[] = [
                    'time' => $key,
                    'kelas' => $occupiedBy[$key] ?? null,
                    'rowspan' => $spanOf[$key] ?? 1,
                    'isBreak' => $isBreak,
                ];
            }

            $cursor->addMinutes($slotMinutes);
        }

        // Sesi yang start_time-nya di luar jam_buka-jam_tutup (data
        // legacy/manual di luar jam operasional) -- disisipkan sebagai
        // baris tambahan di akhir supaya tetap kelihatan, bukan hilang.
        foreach ($outsideGrid as $kelas) {
            $rows[] = [
                'time' => $kelas->start_time->format('H:i').' (di luar jam operasional)',
                'kelas' => $kelas,
                'rowspan' => 1,
                'isBreak' => false,
            ];
        }

        return $rows;
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        $penggantiDariSesi = $request->query('pengganti_dari_sesi_id')
            ? $this->findOrFail($context, $request->query('pengganti_dari_sesi_id'))
            : null;

        // ruangan_id + start_time (prefill, BUKAN dikunci -- start_time
        // tetap boleh digeser admin) datang dari klik "+" di slot kosong
        // grid index() (lihat jadwal-kelas/index.blade.php) -- lihat
        // class docblock.
        return view('jadwal.jadwal-kelas.create', [
            'kelas' => null,
            'selectedBranchOfficeId' => $request->query('branch_office_id') ?? $penggantiDariSesi?->branch_office_id,
            'selectedMataPelajaranId' => $request->query('jadwal_mata_pelajaran_id') ?? $penggantiDariSesi?->jadwal_mata_pelajaran_id,
            'selectedPengajarId' => $request->query('pengajar_id') ?? $penggantiDariSesi?->pengajar_id,
            'selectedStudentId' => $request->query('student_id') ?? $penggantiDariSesi?->student_id,
            'selectedRuanganId' => $request->query('ruangan_id') ?? $penggantiDariSesi?->jadwal_ruangan_id,
            'prefillStartTime' => $request->query('start_time'),
            'returnRuanganId' => $request->query('ruangan_id'),
            'returnDate' => $request->query('date'),
            // Mode grid asal (lihat class docblock update 7 September
            // 2026) -- dibawa lewat hidden input di form supaya
            // store() tahu tab mana yang dituju setelah simpan.
            'returnGroupBy' => $request->query('group_by') === 'pengajar' ? 'pengajar' : 'ruangan',
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

        // Mode grid (ruangan/pengajar) admin datang dari -- dibawa lewat
        // hidden input `group_by` di form (lihat jadwal-kelas/create.
        // blade.php), dipakai gridRedirectParams() di bawah supaya
        // admin balik ke tab yang sama setelah simpan, lihat class
        // docblock update 7 September 2026.
        $groupBy = $request->input('group_by') === 'pengajar' ? 'pengajar' : 'ruangan';

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.kelas.create', $request->only([
                    'branch_office_id', 'jadwal_mata_pelajaran_id', 'pengajar_id', 'student_id',
                    'ruangan_id', 'date', 'start_time', 'group_by',
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
                    ->route('jadwal.kelas.index', $this->gridRedirectParams($penggantiDariSesi, $groupBy))
                    ->with('error', 'Sesi ini baru saja dibuatkan sesi pengganti oleh admin lain.');
            }

            throw $e;
        }

        // Kembali ke grid (tab Ruangan/Pengajar + tanggal) sesi ini
        // berada -- lihat class docblock soal index() jadi grid, bukan
        // lagi di-scope ke student (index() tidak lagi punya mode itu).
        return redirect()
            ->route('jadwal.kelas.index', $this->gridRedirectParams($kelas, $groupBy))
            ->with('success', $penggantiDariSesi
                ? 'Sesi pengganti berhasil dibuat.'
                : 'Jadwal Kelas berhasil ditambahkan.');
    }

    /**
     * Route params untuk balik ke index() grid (tab yang tepat + tanggal
     * sesi ini) setelah create/update/destroy. `$groupBy` menentukan
     * SUMBU tab yang dituju (lihat class docblock update 7 September
     * 2026): 'pengajar' -> tab Pengajar ($kelas->pengajar_id, SELALU
     * ada, wajib diisi); 'ruangan' (default) -> tab Ruangan ('none'
     * kalau sesi ini belum ada ruangannya). Tanggal hari ini kalau
     * sesi ini belum ada start_time-nya (lihat noTimeInRoom/
     * noTimeForPengajar di index()).
     */
    private function gridRedirectParams(JadwalKelas $kelas, string $groupBy = 'ruangan'): array
    {
        $date = $kelas->start_time?->toDateString() ?? now()->toDateString();

        if ($groupBy === 'pengajar') {
            return [
                'group_by' => 'pengajar',
                'pengajar_id' => $kelas->pengajar_id,
                'date' => $date,
            ];
        }

        return [
            'ruangan_id' => $kelas->jadwal_ruangan_id ?: 'none',
            'date' => $date,
        ];
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
            // Mode grid asal (lihat class docblock update 7 September
            // 2026) -- link "Kembali"/hidden input form ikut mode ini
            // supaya update() balik ke tab yang sama.
            'groupByReturn' => $request->query('group_by') === 'pengajar' ? 'pengajar' : 'ruangan',
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

        $groupBy = $request->input('group_by') === 'pengajar' ? 'pengajar' : 'ruangan';

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.kelas.edit', ['id' => $id, 'group_by' => $groupBy])
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
            ->route('jadwal.kelas.index', $this->gridRedirectParams($kelas, $groupBy))
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
        $groupBy = $request->input('group_by') === 'pengajar' ? 'pengajar' : 'ruangan';
        $redirectParams = $this->gridRedirectParams($kelas, $groupBy);
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
            // -- dipakai form supaya sesi manual bisa langsung ditaruh
            // di grid Ruangan yang tepat, lihat class docblock.
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
    }
}
