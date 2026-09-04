<?php

namespace App\Http\Controllers\Jadwal;

use App\Exports\Jadwal\JadwalKelasExport;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalBranchSetting;
use App\Models\JadwalKategori;
use App\Models\JadwalKelas;
use App\Models\JadwalMataPelajaran;
use App\Models\JadwalPengajarKategori;
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
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    /**
     * Update 4 September 2026 (permintaan user, dari contoh gambar
     * grid Excel yang dikirim): index() DIGANTI TOTAL lagi dari tabel
     * flat jadi GRID per jam, di-tab per Ruangan -- iterasi KETIGA di
     * riwayat halaman ini (lihat class docblock: flat -> grid lama ->
     * flat lagi -> grid BARU ini, beda dari grid lama yang di-tab per
     * Ruangan/Pengajar). Alasan eksplisit user: "page ini yang akan di
     * pantau terus oleh admin" -- satu tab Ruangan menampilkan SATU
     * tabel berisi SEMUA slot waktu (durasi = `durasi_sesi_default_menit`
     * branch) dari `jam_buka` sampai `jam_tutup` untuk TANGGAL yang
     * difilter (bukan lagi bisa "semua tanggal" -- grid butuh satu
     * sumbu waktu, `date` SELALU di-default hari ini kalau kosong).
     * Jam istirahat ditandai (baris `table-danger`, tidak bisa diisi).
     * Baris yang ADA App\Models\JadwalKelas beneran (match jam mulainya
     * masuk rentang slot itu) menampilkan datanya PERSIS seperti kolom
     * tabel lama (Pengajar/Bidang/Kategori/Murid/Mulai/Selesai/
     * Kehadiran/Status/Aksi, markup di-extract ke
     * jadwal-kelas/_sesi-row.blade.php supaya sama persis dipakai di
     * grid maupun daftar "Tanpa Jam" di bawah) -- baris tanpa sesi
     * kosong. Ditegaskan user (lewat AskUserQuestion): TIDAK menambah
     * langkah pilih Ruangan di alur checklist Tambah/Edit Student --
     * fitur ini MURNI tampilan, sesi yang `jadwal_ruangan_id`-nya belum
     * ke-isi (semua sesi auto-generate dari checklist Student SELAMA
     * INI selalu begitu, lihat JadwalStudentController::
     * createRutinFromSlots()) masuk tab "Tanpa Ruangan" tersendiri di
     * akhir (cuma muncul kalau memang ada datanya), admin bisa isi
     * Ruangan-nya manual kalau perlu lewat Edit (field itu sudah ada
     * dari dulu, tidak berubah).
     *
     * Sesi yang start_time-nya null ("slot kosong" tanpa jam sama
     * sekali) ATAU jatuh di luar rentang jam_buka-jam_tutup (data lama/
     * anomali, atau branch belum punya Jam Operasional/hari ini bukan
     * hari operasional -- grid-nya jadi kosong semua) TETAP ditampilkan,
     * bukan hilang begitu saja -- masuk daftar "Sesi Tanpa Jam / Di
     * Luar Jam Operasional" di ATAS grid tiap tab (lihat
     * buildRuanganGrid()) supaya tidak ada data yang diam-diam tidak
     * kelihatan gara-gara redesign ini.
     *
     * Pagination DIHAPUS (grid dibatasi jam_buka-jam_tutup 1 hari,
     * jumlah baris wajar tanpa perlu di-page). Filter Pengajar/Mata
     * Pelajaran tetap ada, cuma MENYARING isi grid (baris di luar
     * filter jadi kosong), bukan menghilangkan slot-nya. Filter Branch
     * BARU (dropdown, cuma muncul kalau company punya >1 branch DAN
     * user bukan locked ke satu branch) -- WAJIB ada satu branch aktif
     * supaya Jam Operasional-nya jelas dipakai yang mana; default ke
     * branch PERTAMA kalau belum pernah pilih.
     */
    public function index(Request $request): View
    {
        $f = $this->resolveFilteredSesi($request);
        $context = $f['context'];
        $company = $f['company'];
        $branchSetting = $f['branchSetting'];
        $isHariOperasional = $f['isHariOperasional'];
        $ruangans = $f['ruangans'];
        $sesiByRuangan = $f['sesiByRuangan'];

        $timeSlots = ($branchSetting && $isHariOperasional) ? $this->buildTimeSlots($branchSetting) : collect();

        $ruanganTabs = $ruangans->map(fn (JadwalRuangan $ruangan) => [
            'id' => $ruangan->id,
            'name' => $ruangan->name,
        ] + $this->buildRuanganGrid($timeSlots, $sesiByRuangan->get($ruangan->id, collect())));

        // "Tanpa Ruangan" -- lihat docblock method ini -- cuma
        // ditambahkan sebagai tab kalau memang ada datanya, supaya
        // tidak nambah tab kosong kalau semua sesi sudah rapi ke-assign
        // Ruangan-nya.
        $tanpaRuanganSesi = $sesiByRuangan->get(null, collect());
        if ($tanpaRuanganSesi->isNotEmpty()) {
            $ruanganTabs->push([
                'id' => 'tanpa-ruangan',
                'name' => 'Tanpa Ruangan',
            ] + $this->buildRuanganGrid($timeSlots, $tanpaRuanganSesi));
        }

        return view('jadwal.jadwal-kelas.index', [
            'ruanganTabs' => $ruanganTabs,
            'branchSetting' => $branchSetting,
            'isHariOperasional' => $isHariOperasional,
            'carbonDate' => $f['carbonDate'],
            'pengajars' => $this->companyPengajarMembers($company, $f['branchOfficeId']),
            'mataPelajarans' => JadwalMataPelajaran::where('company_id', $company->id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'branchOffices' => $f['branchOffices'],
            'filterDate' => $f['date'],
            'filterPengajarId' => $f['pengajarId'],
            'filterMataPelajaranId' => $f['mataPelajaranId'],
            'branchOfficeId' => $f['branchOfficeId'],
            // Update 4 September 2026 (permintaan user: "tambahkan
            // fungsi export to excel sesuai dengan filter" & "fungsi
            // print sesuai dengan filter") -- label filter yang lagi
            // aktif, MURNI dipakai buat header halaman cetak (lihat
            // index.blade.php's @media print) supaya kertas yang
            // dicetak tetap jelas ini "Jadwal Kelas" filter yang mana,
            // walau chrome (navbar/sidebar/form filter) disembunyikan
            // saat dicetak. Tombol "Export ke Excel" & "Cetak" juga baru
            // di sini -- lihat export() untuk Excel-nya.
            'filterPengajarName' => $f['pengajarName'],
            'filterMataPelajaranName' => $f['mataPelajaranName'],
            'filterBranchName' => $f['branchName'],
            // Update 4 September 2026 (permintaan user: "perbaiki fungsi
            // edit nya ... di buat modern popup saja ... pilih dulu
            // kelas dan category nya, pilih pengajar nya baru tampilkan
            // jam pengajarnya berupa tab") -- data pendukung popup Edit
            // (dibuka dari tombol pensil tiap baris, lihat _sesi-row.
            // blade.php & index.blade.php) di-embed SEKALI di sini
            // (bukan AJAX per klik admin buka popup) supaya popup-nya
            // terasa instan. Lihat docblock editModalData().
            'students' => JadwalStudent::where('company_id', $company->id)
                ->where('status', 'active')
                ->with(['mataPelajaran:id,name', 'pengajar:id,name'])
                ->orderBy('name')
                ->get(),
            'ruangans' => $ruangans,
        ] + $this->editModalData($context, $f['carbonDate']));
    }

    /**
     * Update 4 September 2026 (permintaan user: "tambahkan fungsi
     * export to excel sesuai dengan filter"): export SEMUA sesi yang
     * cocok filter index() (Tanggal/Pengajar/Mata Pelajaran/Branch) ke
     * Excel, SATU sheet per tab Ruangan (termasuk "Tanpa Ruangan" kalau
     * ada) -- struktur sheet-nya mengikuti tab di layar, TAPI isinya
     * cuma baris App\Models\JadwalKelas yang BENERAN ADA (tidak meniru
     * baris kosong grid di layar -- Excel yang isinya baris kosong
     * berulang cuma bikin file panjang tanpa guna), diurutkan ascending
     * by start_time. Filter dijamin SAMA PERSIS dengan yang di layar
     * lewat resolveFilteredSesi() (SATU SUMBER dipakai index() DAN
     * export() ini) -- tidak mungkin filter Excel "kelewatan" beda dari
     * yang sedang dilihat admin di layar.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $f = $this->resolveFilteredSesi($request);

        $sheets = $f['ruangans']->map(fn (JadwalRuangan $r) => [
            'name' => $r->name,
            'sesi' => $f['sesiByRuangan']->get($r->id, collect())->sortBy('start_time')->values(),
        ])->values();

        $tanpaRuanganSesi = $f['sesiByRuangan']->get(null, collect());
        if ($tanpaRuanganSesi->isNotEmpty()) {
            $sheets->push(['name' => 'Tanpa Ruangan', 'sesi' => $tanpaRuanganSesi->sortBy('start_time')->values()]);
        }

        // Tetap export SATU sheet (kosong) kalau memang tidak ada
        // Ruangan aktif & tidak ada sesi tanpa Ruangan sama sekali --
        // lebih jelas daripada file Excel tanpa sheet sama sekali
        // (invalid/bikin error dibuka Excel).
        if ($sheets->isEmpty()) {
            $sheets->push(['name' => 'Jadwal Kelas', 'sesi' => collect()]);
        }

        $filename = 'jadwal-kelas-'.$f['company']->slug.'-'.$f['date'].'.xlsx';

        return Excel::download(
            new JadwalKelasExport($sheets->all(), $f['carbonDate'], $f['branchName'], $f['pengajarName'], $f['mataPelajaranName']),
            $filename
        );
    }

    /**
     * Resolve context perusahaan + filter (Branch/Tanggal/Pengajar/Mata
     * Pelajaran, sama urutan resolusi seperti index() sebelum di-
     * refactor) DAN query mentah App\Models\JadwalKelas yang cocok
     * filter itu, dikelompokkan per `jadwal_ruangan_id` -- di-extract
     * dari index() (Update 4 September 2026, permintaan fitur Export
     * Excel & Cetak) supaya SATU SUMBER filter dipakai index() (grid
     * layar), export() (Excel), DAN halaman itu sendiri saat dicetak
     * (cetak murni CSS @media print di atas HTML yang sama dengan
     * index(), bukan route terpisah -- lihat index.blade.php) --
     * menjamin ketiganya TIDAK PERNAH menampilkan/meng-export data yang
     * beda dari filter yang sedang aktif.
     *
     * @return array{context: mixed, company: Company, branchOffices: Collection, branchOfficeId: ?string, date: string, pengajarId: ?string, mataPelajaranId: ?string, branchSetting: ?JadwalBranchSetting, carbonDate: Carbon, isHariOperasional: bool, ruangans: Collection, sesiByRuangan: Collection, pengajarName: ?string, mataPelajaranName: ?string, branchName: ?string}
     */
    private function resolveFilteredSesi(Request $request): array
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOffices = $context->isLockedToBranch()
            ? collect()
            : BranchOffice::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : ($request->query('branch_office_id') ?: $branchOffices->first()?->id);

        // `date` SELALU ke-isi (grid butuh satu sumbu waktu) -- beda
        // dari versi tabel flat sebelumnya yang bisa dikosongkan untuk
        // "semua tanggal" (konsep itu tidak berlaku lagi di grid).
        $date = $request->query('date') ?: now()->toDateString();
        $pengajarId = $request->query('pengajar_id');
        $mataPelajaranId = $request->query('jadwal_mata_pelajaran_id');

        $branchSetting = $branchOfficeId
            ? JadwalBranchSetting::where('branch_office_id', $branchOfficeId)->first()
            : null;

        $carbonDate = Carbon::parse($date);
        $isHariOperasional = $branchSetting?->isHariOperasional($carbonDate->dayOfWeek) ?? false;

        $ruangans = $branchOfficeId
            ? JadwalRuangan::where('company_id', $company->id)
                ->where('branch_office_id', $branchOfficeId)
                ->where('status', JadwalRuangan::STATUS_ACTIVE)
                ->orderBy('name')
                ->get()
            : collect();

        $sesiQuery = JadwalKelas::where('company_id', $company->id)
            ->with([
                'pengajar:id,name', 'student:id,name', 'mataPelajaran:id,name', 'kategori:id,name',
                'sesiPengganti:id,pengganti_dari_sesi_id,start_time',
                'penggantiDariSesi:id,start_time',
            ])
            ->whereDate('start_time', $date);

        if ($branchOfficeId) {
            $sesiQuery->where(function ($q) use ($branchOfficeId) {
                $q->where('branch_office_id', $branchOfficeId)->orWhereNull('branch_office_id');
            });
        }

        if ($pengajarId) {
            $sesiQuery->where('pengajar_id', $pengajarId);
        }

        if ($mataPelajaranId) {
            $sesiQuery->where('jadwal_mata_pelajaran_id', $mataPelajaranId);
        }

        $sesiByRuangan = $sesiQuery->get()->groupBy('jadwal_ruangan_id');

        // Label nama filter yang aktif -- dipakai header halaman cetak
        // (index.blade.php) & baris info tiap sheet Excel (JadwalKelasExport),
        // dihitung SEKALI di sini (bukan duplikat di index()/export())
        // supaya konsisten.
        $pengajarName = $pengajarId
            ? $this->companyPengajarMembers($company, $branchOfficeId)->firstWhere('id', $pengajarId)?->name
            : null;
        $mataPelajaranName = $mataPelajaranId
            ? JadwalMataPelajaran::where('company_id', $company->id)->where('id', $mataPelajaranId)->value('name')
            : null;
        $branchName = $branchOfficeId
            ? BranchOffice::where('company_id', $company->id)->where('id', $branchOfficeId)->value('name')
            : null;

        return compact(
            'context', 'company', 'branchOffices', 'branchOfficeId', 'date', 'pengajarId', 'mataPelajaranId',
            'branchSetting', 'carbonDate', 'isHariOperasional', 'ruangans', 'sesiByRuangan',
            'pengajarName', 'mataPelajaranName', 'branchName'
        );
    }

    /**
     * Update 4 September 2026 (permintaan user: "perbaiki fungsi edit
     * nya ya dari jadwal kelas, itu masih yang lama, di buat modern
     * popup saja. jadi pilih dulu kelas dan category nya, pilih
     * pengajar nya baru tampilkan jam pengajarnya berupa tab seperti
     * sblm sblmnya"). Sebelumnya Edit Jadwal Kelas cuma halaman penuh
     * dengan dropdown Pengajar bebas + 2 field datetime-local mentah
     * (jadwal-kelas/edit.blade.php lewat _form.blade.php) -- admin
     * harus ketik tanggal & jam manual sendiri, tidak tahu jam
     * ketersediaan Pengajar itu apa.
     *
     * Update 7 September 2026 (permintaan user, SETELAH popup pertama
     * di atas jalan: "yang harus di modifikasi adalah tampilkan seluruh
     * pengajar dan jam pengajar yang available atau yang kosong seperti
     * di student. triger pertama kali itu pilih pengajar, ketika
     * pengajar itu dipilih baru keluarkan bidang dan category di
     * lanjut dengan jam pelajaran yang available. mngkn murid itu mau
     * pindah pengajar dan pindah kelas juga jadi, 1 rangkaian pindah")
     * -- URUTAN PEMICU DIBALIK dari versi pertama: SEKARANG (1) admin
     * pilih Pengajar DULU (dropdown isinya SEMUA pengajar yang punya
     * penugasan Kategori aktif, bukan lagi menunggu Kategori dipilih
     * dulu), (2) begitu Pengajar dipilih, dropdown Bidang & Kategori
     * ke-filter ke yang DIAJARKAN pengajar itu (lihat pengajarBidangMap
     * & pengajarKategoriMap di bawah, KEBALIKAN arah dari
     * kategoriPengajarMap versi pertama), (3) begitu Kategori dipilih,
     * panel "Jam Pengajar" tampil (pola tab per hari, sama seperti
     * sebelumnya -- lihat komentar index.blade.php). Alasannya (kata
     * user): admin sering perlu PINDAHKAN murid ke pengajar lain
     * SEKALIGUS ke kelas/kategori lain dalam satu tindakan -- mulai
     * dari "pengajar mana yang mau dipakai" itu alur yang lebih natural
     * daripada mulai dari Kategori.
     *
     * Klik satu jam otomatis mengisi Tanggal/Waktu Mulai/Waktu Selesai
     * (field itu TETAP bisa diisi manual, tidak dikunci -- popup ini
     * MURNI kemudahan pengisian, bukan pembatasan baru). Pemecahan jam
     * ketersediaan pengajar per durasi sesi default branch pakai
     * splitJamIntoChunks() di bawah, SENGAJA duplikat murni dari
     * JadwalStudentController::splitSlotIntoChunks() (bukan di-extract
     * ke service bersama) supaya popup ini TIDAK menyentuh
     * JadwalStudentController.php sama sekali, sesuai instruksi user
     * berulang kali sesi ini: "jangan sampai kena fungsi lain".
     *
     * TIDAK menambah pengecekan bentrok di SERVER yang MENOLAK submit
     * (beda dari App\Services\Jadwal\JadwalRutinConflictService yang
     * dipakai alur JadwalRutin mingguan) -- App\Models\JadwalKelas di
     * sini satu sesi bertanggal PASTI, bukan pola mingguan berulang,
     * jadi butuh desain pengecekan bentrok server yang beda kalau mau
     * ditolak (lawan JadwalKelas lain persis di tanggal itu, bukan
     * lawan JadwalRutin) -- di luar permintaan user, sengaja tidak
     * ditambah supaya tidak melebar. Yang DITAMBAHKAN (permintaan
     * "tampilkan ... yang available atau yang kosong seperti di
     * student", lalu ditegaskan lagi "harus nya yg terisi itu tidak
     * bisa diklik") adalah info Kosong/Terisi tiap jam chip (badge,
     * lihat $bookedSlots di bawah) DAN chip "Terisi" itu DI-DISABLE di
     * SISI KLIEN (index.blade.php's renderJamPanel(), atribut HTML
     * `disabled`) supaya admin tidak bisa klik jam yang sudah kepakai
     * -- TETAP BUKAN validasi server (kalau admin isi jam bentrok itu
     * manual lewat field Waktu Mulai/Selesai, atau lewat request
     * langsung, tetap lolos -- proteksinya murni kemudahan di UI popup
     * ini, bukan aturan bisnis baru). Dihitung lawan App\Models\
     * JadwalKelas lain yang jam mulainya jatuh di TANGGAL yang lagi
     * difilter admin di grid ($carbonDate, lihat $bookedSlotsDate) --
     * data di-embed statis SEKALI (bukan AJAX) jadi cuma akurat untuk
     * tanggal itu; index.blade.php's script menyembunyikan badge (jam
     * jadi selalu bisa diklik lagi) kalau admin geser field Tanggal di
     * popup ke tanggal lain (tidak ada dasar data utk tanggal lain).
     *
     * Data untuk SEMUA Pengajar+Kategori+jam ketersediaan company ini
     * di-embed SEKALIGUS (lihat pemakaian di index.blade.php's
     * <script>) supaya popup terasa instan begitu admin klik pensil --
     * tidak perlu request baru ke server tiap kali ganti pilihan
     * Pengajar/Bidang/Kategori di dalam popup.
     *
     * @return array{allPengajars: Collection, pengajarBidangMap: array, pengajarKategoriMap: array, pengajarSlotMap: array, bookedSlots: array, bookedSlotsDate: string}
     */
    private function editModalData($context, Carbon $carbonDate): array
    {
        $pengajarKategoris = JadwalPengajarKategori::with(['jadwals', 'pengajar:id,name', 'kategori.mataPelajaran'])
            ->where('company_id', $context->company->id)
            ->where('status', JadwalPengajarKategori::STATUS_ACTIVE)
            ->get();

        $branchSettings = JadwalBranchSetting::where('company_id', $context->company->id)->get()->keyBy('branch_office_id');

        $allPengajars = [];
        $pengajarBidangMap = [];
        $pengajarKategoriMap = [];
        $pengajarSlotMap = [];

        foreach ($pengajarKategoris as $pk) {
            if (! $pk->pengajar || ! $pk->kategori) {
                continue;
            }

            // Dedupe by pengajar_id (satu pengajar wajar punya lebih
            // dari satu penugasan Kategori aktif) -- array_values() di-
            // rapikan jadi list sebelum dikembalikan di bawah.
            $allPengajars[$pk->pengajar_id] = ['id' => $pk->pengajar_id, 'name' => $pk->pengajar->name];

            $mataPelajaran = $pk->kategori->mataPelajaran;

            if ($mataPelajaran) {
                $pengajarBidangMap[$pk->pengajar_id][$mataPelajaran->id] = [
                    'id' => $mataPelajaran->id,
                    'name' => $mataPelajaran->name,
                ];
            }

            $pengajarKategoriMap[$pk->pengajar_id][$pk->jadwal_kategori_id] = [
                'id' => $pk->jadwal_kategori_id,
                'name' => $pk->kategori->name,
                'jadwal_mata_pelajaran_id' => $mataPelajaran?->id,
            ];

            $branchOfficeId = $mataPelajaran?->branch_office_id;
            $branchSetting = $branchOfficeId ? $branchSettings->get($branchOfficeId) : null;
            $durasi = $branchSetting?->durasi_sesi_default_menit ?: 30;

            $slots = $pk->jadwals
                ->flatMap(fn ($slot) => collect($this->splitJamIntoChunks(substr($slot->jam_mulai, 0, 5), substr($slot->jam_selesai, 0, 5), $durasi))
                    ->map(fn ($chunk) => [
                        'hari' => $slot->hari,
                        'jam_mulai' => $chunk['jam_mulai'],
                        'jam_selesai' => $chunk['jam_selesai'],
                    ]))
                // Sama seperti validator jam manual di bawah: dilewati
                // (tidak menyaring) kalau branch Mata Pelajaran Kategori
                // ini belum punya Jam Operasional diatur -- tidak ada
                // dasar buat menyembunyikan jam yang pengajar itu
                // sendiri daftarkan kalau memang belum ada aturannya.
                ->when($branchSetting, fn ($c) => $c->filter(fn (array $chunk) => $branchSetting->isHariOperasional($chunk['hari'])
                    && $branchSetting->isWithinOperationalHours($chunk['jam_mulai'], $chunk['jam_selesai'])))
                ->values();

            $pengajarSlotMap[$pk->pengajar_id][$pk->jadwal_kategori_id] = $slots;
        }

        // Update 7 September 2026 (lihat docblock di atas) -- jam yang
        // sudah kepakai App\Models\JadwalKelas LAIN pada tanggal yang
        // lagi difilter admin di grid, per pengajar. Nama murid ikut
        // dibawa (kalau ada) MURNI supaya tooltip badge "Terisi" di
        // popup lebih informatif ("bentrok dengan sesi murid X").
        $bookedSlots = [];
        JadwalKelas::where('company_id', $context->company->id)
            ->whereNotNull('pengajar_id')
            ->whereNotNull('start_time')
            ->whereDate('start_time', $carbonDate)
            ->with('student:id,name')
            ->get(['id', 'pengajar_id', 'student_id', 'start_time', 'end_time'])
            ->each(function (JadwalKelas $k) use (&$bookedSlots) {
                $bookedSlots[$k->pengajar_id][] = [
                    'kelas_id' => $k->id,
                    'start' => $k->start_time->format('H:i'),
                    'end' => $k->end_time ? $k->end_time->format('H:i') : $k->start_time->format('H:i'),
                    'student_name' => $k->student?->name,
                ];
            });

        return [
            'allPengajars' => collect($allPengajars)->values()->sortBy('name')->values(),
            'pengajarBidangMap' => collect($pengajarBidangMap)->map(fn ($byId) => collect($byId)->values()->sortBy('name')->values())->all(),
            'pengajarKategoriMap' => collect($pengajarKategoriMap)->map(fn ($byId) => collect($byId)->values()->sortBy('name')->values())->all(),
            'pengajarSlotMap' => $pengajarSlotMap,
            'bookedSlots' => $bookedSlots,
            'bookedSlotsDate' => $carbonDate->toDateString(),
        ];
    }

    /**
     * Pecah satu rentang jam ketersediaan Pengajar (App\Models\
     * JadwalPengajarJadwal) jadi chunk-chunk per durasi sesi default
     * branch -- lihat docblock editModalData(). Sama persis algoritmanya
     * dengan JadwalStudentController::splitSlotIntoChunks(), sengaja
     * DIDUPLIKASI (bukan di-extract ke satu service bersama) supaya
     * popup Edit Jadwal Kelas ini tidak menyentuh file
     * JadwalStudentController.php sama sekali.
     *
     * @return list<array{jam_mulai: string, jam_selesai: string}>
     */
    private function splitJamIntoChunks(string $jamMulai, string $jamSelesai, int $durasiMenit): array
    {
        if ($durasiMenit <= 0) {
            return [];
        }

        $chunks = [];
        $cursor = Carbon::createFromFormat('H:i', $jamMulai);
        $end = Carbon::createFromFormat('H:i', $jamSelesai);

        while ($cursor->copy()->addMinutes($durasiMenit)->lte($end)) {
            $chunkEnd = $cursor->copy()->addMinutes($durasiMenit);
            $chunks[] = ['jam_mulai' => $cursor->format('H:i'), 'jam_selesai' => $chunkEnd->format('H:i')];
            $cursor = $chunkEnd;
        }

        return $chunks;
    }

    /**
     * Pecah jam_buka..jam_tutup branch jadi list slot waktu tetap
     * `durasi_sesi_default_menit` (pola sama dengan
     * JadwalStudentController::splitSlotIntoChunks() -- sisa di ujung
     * yang kurang dari satu durasi penuh SENGAJA dibuang), tiap slot
     * ditandai `istirahat` kalau tumpang tindih jam_istirahat_mulai/
     * selesai. Dipakai buildRuanganGrid() jadi baris grid tiap tab
     * Ruangan.
     *
     * @return Collection<int, array{start: string, end: string, istirahat: bool}>
     */
    private function buildTimeSlots(JadwalBranchSetting $branchSetting): Collection
    {
        $durasi = $branchSetting->durasi_sesi_default_menit ?: 30;
        $jamBuka = substr($branchSetting->jam_buka, 0, 5);
        $jamTutup = substr($branchSetting->jam_tutup, 0, 5);
        $istirahatMulai = $branchSetting->jam_istirahat_mulai ? substr($branchSetting->jam_istirahat_mulai, 0, 5) : null;
        $istirahatSelesai = $branchSetting->jam_istirahat_selesai ? substr($branchSetting->jam_istirahat_selesai, 0, 5) : null;

        $slots = collect();
        $cursor = Carbon::createFromFormat('H:i', $jamBuka);
        $end = Carbon::createFromFormat('H:i', $jamTutup);

        while ($cursor->copy()->addMinutes($durasi)->lte($end)) {
            $slotStart = $cursor->format('H:i');
            $slotEnd = $cursor->copy()->addMinutes($durasi)->format('H:i');

            $slots->push([
                'start' => $slotStart,
                'end' => $slotEnd,
                'istirahat' => $istirahatMulai && $istirahatSelesai
                    && $slotStart < $istirahatSelesai && $slotEnd > $istirahatMulai,
            ]);

            $cursor->addMinutes($durasi);
        }

        return $slots;
    }

    /**
     * Bangun baris grid (satu per slot dari $timeSlots, tiap slot dapat
     * property `sesi` -- Collection App\Models\JadwalKelas yang jam
     * mulainya jatuh dalam slot itu, kosong kalau tidak ada) PLUS daftar
     * `unmatched` -- sesi di $sesiList yang TIDAK ke-plot ke slot manapun
     * (start_time null, atau di luar rentang $timeSlots sama sekali,
     * termasuk kalau $timeSlots kosong karena branch belum punya Jam
     * Operasional / hari ini bukan hari operasional) -- lihat docblock
     * index() soal kenapa ini penting (supaya tidak ada sesi yang diam-
     * diam hilang dari tampilan gara-gara redesign grid ini).
     *
     * @return array{rows: Collection, unmatched: Collection}
     */
    private function buildRuanganGrid(Collection $timeSlots, Collection $sesiList): array
    {
        $matchedIds = [];

        $rows = $timeSlots->map(function (array $slot) use ($sesiList, &$matchedIds) {
            if ($slot['istirahat']) {
                return $slot + ['sesi' => collect()];
            }

            $inSlot = $sesiList->filter(function (JadwalKelas $kelas) use ($slot) {
                if (! $kelas->start_time) {
                    return false;
                }

                $t = $kelas->start_time->format('H:i');

                return $t >= $slot['start'] && $t < $slot['end'];
            })->values();

            foreach ($inSlot as $kelas) {
                $matchedIds[] = $kelas->id;
            }

            return $slot + ['sesi' => $inSlot];
        });

        $unmatched = $sesiList->reject(fn (JadwalKelas $kelas) => in_array($kelas->id, $matchedIds, true))->values();

        return ['rows' => $rows, 'unmatched' => $unmatched];
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
                // Update 4 September 2026 (popup Edit Jadwal Kelas, lihat
                // docblock editModalData()): 'jadwal_kategori_id' baru
                // sekarang bisa diisi dari validator() -- sebelumnya field
                // ini TIDAK PERNAH diproses store()/update() sama sekali
                // (kolomnya sudah lama ada di skema, cuma tidak pernah
                // ke-set dari form manual). $snapshot di atas (alur sesi
                // PENGGANTI) tetap menang lewat array_merge() di bawah
                // kalau ini pengganti sesi -- perilaku situ TIDAK berubah.
                'jadwal_kategori_id' => $validated['jadwal_kategori_id'] ?? null,
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

    /**
     * Update 4 September 2026 (permintaan user: popup Edit modern, lihat
     * docblock editModalData()): halaman penuh ini TIDAK LAGI jadi jalur
     * utama admin edit Jadwal Kelas -- tombol pensil tiap baris di
     * index() sekarang membuka popup langsung di halaman grid (lihat
     * index.blade.php's #editKelasModal), tidak pindah ke sini lagi.
     * Route/method/view ini SENGAJA DIBIARKAN APA ADANYA (bukan dihapus)
     * jadi fallback kalau URL-nya diakses langsung -- lebih aman
     * daripada menghapus jalur yang sudah teruji, sesuai instruksi user
     * berulang kali sesi ini soal hati-hati scope komit.
     */
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
            // Update 4 September 2026 (popup Edit modern, lihat docblock
            // editModalData()): SEBELUMNYA redirect balik ke halaman
            // penuh jadwal.kelas.edit -- sekarang popup-nya hidup di
            // index() (grid), jadi validasi gagal balik ke SANA (dengan
            // filter yang relevan dengan sesi ini, lihat
            // filterRedirectParams()) supaya admin tetap di popup yang
            // sama, bukan terlempar ke halaman lama yang sudah tidak
            // dipakai lagi. `reopenEditKelasId` (flash session, BUKAN
            // input) dibaca index.blade.php's <script> untuk tahu popup
            // MANA yang harus dibuka ulang otomatis + diisi ulang dari
            // old() supaya isian admin yang gagal disimpan TIDAK hilang.
            return redirect()
                ->route('jadwal.kelas.index', $this->filterRedirectParams($kelas))
                ->withErrors($validator)
                ->withInput()
                ->with('reopenEditKelasId', $id);
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
            // Update 4 September 2026: lihat catatan yang sama di store().
            'jadwal_kategori_id' => $validated['jadwal_kategori_id'] ?? null,
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
        // Update 4 September 2026 (popup Edit modern, lihat docblock
        // editModalData()): sama seperti jadwal_ruangan_id di atas --
        // <select name="jadwal_kategori_id"> yang dikosongkan kirim
        // string kosong, dinormalisasi ke null SEBELUM validasi.
        if ($request->has('jadwal_kategori_id') && $request->input('jadwal_kategori_id') === '') {
            $request->merge(['jadwal_kategori_id' => null]);
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
            // Update 4 September 2026 (popup Edit modern, lihat docblock
            // editModalData()): field ini SUDAH LAMA ada di skema
            // (App\Models\JadwalKelas::$fillable) tapi belum pernah
            // divalidasi/diproses store()/update() -- popup Edit yang
            // baru sekarang mengirimkannya (langkah "pilih dulu kelas
            // dan category nya"). Nullable/opsional sama seperti
            // jadwal_ruangan_id di atas -- Kategori bukan hal wajib utk
            // sesi manual.
            'jadwal_kategori_id' => [
                'nullable', 'uuid', 'exists:jadwal_kategori,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalKategori::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Kategori tidak valid.');
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
