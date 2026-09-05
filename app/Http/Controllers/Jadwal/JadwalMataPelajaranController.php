<?php

namespace App\Http\Controllers\Jadwal;

use App\Helpers\JadwalImageUploader;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalKelas;
use App\Models\JadwalMataPelajaran;
use App\Services\Jadwal\JadwalCountsService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * CRUD for "Mata Pelajaran / Bidang" (Jadwal > Mata Pelajaran / Bidang)
 * — the subject/field catalog (musik, bahasa, dst.) App\Models\
 * JadwalKelas optionally belongs to. Branch scoping follows the same
 * rule as Chat\CategoryPhoneBookController: an owner sees/manages every
 * row, a branch-locked member only their own branch's (plus any row
 * with no branch set).
 */
class JadwalMataPelajaranController extends Controller
{
    use ResolvesCompanyContext;

    private const IMAGE_SUBDIRECTORY = 'mata-pelajaran';

    public function __construct(
        protected JadwalCountsService $countsService,
    ) {
    }

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $request->query('branch_office_id');

        $query = JadwalMataPelajaran::where('company_id', $company->id)
            ->withCount('kelas')
            // Jumlah Kategori (App\Models\JadwalKategori) + list-nya
            // sekalian di-eager-load -- relasi langsung (hasMany), tidak
            // perlu correlated subquery kayak pengajar_count/student_count
            // di bawah karena Kategori memang entitas sendiri per Mata
            // Pelajaran, bukan diturunkan dari baris JadwalKelas. Dipakai
            // badge + modal "Kategori" di index.blade.php.
            ->withCount('kategoris')
            ->with(['kategoris' => fn ($q) => $q->orderBy('name')])
            // Fix 4 September 2026 (laporan user: "di mata pelajaran ,
            // pada icon icon tersebut msh ada jumlahnya padahal sudah di
            // hapus") -- SEBELUMNYA pengajar_count/student_count/
            // ruangan_count DIHITUNG DARI BARIS JadwalKelas historis
            // (semua status, tidak pernah difilter aktif/tidak) --
            // komentar lama beralasan "tidak ada tabel assignment
            // pengajar tersendiri", TAPI itu sudah TIDAK BENAR sejak
            // App\Models\JadwalPengajarKategori ada (penugasan Pengajar
            // ke Kategori). Akibatnya: admin hapus penugasan Pengajar
            // (JadwalPengajarController::destroy()) ATAU nonaktifkan/
            // hapus Student -- baris JadwalKelas LAMA yang sudah pernah
            // dibuat TETAP ADA (sengaja tidak ikut dihapus, lihat
            // docblock destroy() itu & JadwalRutinController::destroy())
            // jadi badge di sini TIDAK PERNAH turun, padahal assignment/
            // murid-nya sudah tidak aktif lagi.
            //
            // SEKARANG dihitung dari sumber yang benar-benar "aktif saat
            // ini" per jenisnya (masing-masing correlated subquery,
            // whereColumn ke jadwal_mata_pelajaran.id -- pola sama
            // dengan withCount()/addSelect() yang sudah ada):
            // - pengajar_count: distinct pengajar_id dari
            //   JadwalPengajarKategori status=active yang Kategori-nya
            //   (join ke jadwal_kategori) milik Mata Pelajaran ini --
            //   inilah tabel assignment yang sebenarnya, turun begitu
            //   admin hapus/nonaktifkan penugasan Pengajar.
            // - student_count: JadwalStudent status=active langsung
            //   yang jadwal_mata_pelajaran_id-nya Mata Pelajaran ini --
            //   turun begitu murid di-Nonaktifkan/Hapus Total (lihat
            //   App\Http\Controllers\Jadwal\JadwalStudentController::
            //   deactivate()/destroy()).
            // - ruangan_count: distinct jadwal_ruangan_id dari
            //   JadwalRutin status=active (jadwal MINGGUAN yang
            //   sungguhan masih jalan) yang Kategori-nya milik Mata
            //   Pelajaran ini -- "Ruangan yang SEDANG dipakai", bukan
            //   riwayat Ruangan yang PERNAH dipakai sesi manapun.
            //
            // Badge "Kelas" (kelas_count di atas, withCount('kelas'))
            // SENGAJA TIDAK diubah -- itu tetap TOTAL riwayat sesi
            // sepanjang masa (angka historis, wajar tidak pernah turun),
            // beda konsepnya dari 3 badge "siapa yang aktif SEKARANG" di
            // bawah ini.
            //
            // Refactor 5 September 2026 (permintaan user: "kode makin
            // gemuk, tolong dirapikan") -- 3 subquery di bawah ini
            // dipindah ke App\Services\Jadwal\JadwalCountsService
            // (SATU sumber dipakai bersama menu Pengajar/Mata Pelajaran/
            // Student, lihat docblock class itu), murni relokasi, tidak
            // ada perubahan logic/SQL di sini.
            ->addSelect(['pengajar_count' => $this->countsService->pengajarCountSubquery()])
            ->addSelect(['student_count' => $this->countsService->studentCountSubquery()])
            ->addSelect(['ruangan_count' => $this->countsService->ruanganCountSubquery()])
            ->with('branchOffice:id,name');

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        } elseif ($branchOfficeId) {
            // Index ini dibuka scoped dari index Branch (tombol "+ Add
            // Mata Pelajaran / Bidang") — lihat JadwalBranchController.
            $query->where('branch_office_id', $branchOfficeId);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $mataPelajarans = $query->latest()->paginate(15)->withQueryString()->onEachSide(1);

        $this->attachRoster($mataPelajarans, $company);

        // Konteks Branch (kalau index ini dibuka scoped) — dipakai untuk
        // breadcrumb + tombol "Back to Branch" + mengunci branch_office_id
        // di tombol "+ Add Mata Pelajaran / Bidang".
        $branch = $branchOfficeId
            ? BranchOffice::where('company_id', $company->id)->where('id', $branchOfficeId)->first()
            : null;

        // Konteks Ruangan (kalau index ini dibuka lewat tombol "Add Mata
        // Pelajaran" di halaman Jam Operasional, lihat
        // JadwalBranchSettingController::index()) -- murni dibawa
        // balik-balik lewat query string supaya tombol "Kembali" & "+
        // Tambah" tetap mengarah ke Jam Operasional Ruangan yang sama,
        // TIDAK disimpan ke jadwal_mata_pelajaran (tabel itu tetap milik
        // Branch langsung, tidak berubah, lihat CLAUDE.md item #15).
        $ruanganId = $request->query('ruangan_id');

        return view('jadwal.jadwal-mata-pelajaran.index', compact('mataPelajarans', 'branch', 'branchOfficeId', 'ruanganId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        return view('jadwal.jadwal-mata-pelajaran.create', [
            'mataPelajaran' => null,
            'selectedBranchOfficeId' => $request->query('branch_office_id'),
            'ruanganId' => $request->query('ruangan_id'),
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $ruanganId = $request->input('ruangan_id');

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.mata-pelajaran.create', array_filter(['branch_office_id' => $request->input('branch_office_id'), 'ruangan_id' => $ruanganId]))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        JadwalMataPelajaran::create([
            'company_id' => $company->id,
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image' => $request->hasFile('image')
                ? JadwalImageUploader::upload($request->file('image'), self::IMAGE_SUBDIRECTORY)
                : null,
            'status' => $validated['status'] ?? 'active',
        ]);

        // Kembali ke index yang sudah di-scope ke branch itu (bukan index
        // global) kalau memang dibuat dengan konteks branch — sesuai alur
        // "ina": create -> kembali ke index yang scoped ke parent-nya.
        return redirect()
            ->route('jadwal.mata-pelajaran.index', array_filter(['branch_office_id' => $validated['branch_office_id'] ?? null, 'ruangan_id' => $ruanganId]))
            ->with('success', 'Mata Pelajaran / Bidang berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $mataPelajaran = $this->findOrFail($context, $id);

        return view('jadwal.jadwal-mata-pelajaran.edit', [
            'mataPelajaran' => $mataPelajaran,
            'branchOffices' => $this->branchOfficesFor($context->company, $context),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $mataPelajaran = $this->findOrFail($context, $id);

        if (! $context->isOwner) {
            $request->merge(['branch_office_id' => $context->branchOffice?->id]);
        }

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.mata-pelajaran.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $newImage = $mataPelajaran->image;

        if ($request->hasFile('image')) {
            $newImage = JadwalImageUploader::upload($request->file('image'), self::IMAGE_SUBDIRECTORY);
        } elseif ($request->boolean('remove_image')) {
            $newImage = null;
        }

        if ($newImage !== $mataPelajaran->image) {
            JadwalImageUploader::delete($mataPelajaran->image);
        }

        $mataPelajaran->update([
            'branch_office_id' => $validated['branch_office_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'image' => $newImage,
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.mata-pelajaran.index')
            ->with('success', 'Mata Pelajaran / Bidang berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $mataPelajaran = $this->findOrFail($context, $id);

        JadwalImageUploader::delete($mataPelajaran->image);
        $mataPelajaran->delete();

        return redirect()
            ->route('jadwal.mata-pelajaran.index')
            ->with('success', 'Mata Pelajaran / Bidang berhasil dihapus.');
    }

    /**
     * Ringkasan "siapa mengajar siapa, di ruangan mana" untuk tiap
     * Mata Pelajaran / Bidang di halaman index — dipakai modal yang
     * dibuka dari klik badge Statistik (lihat index.blade.php), supaya
     * owner tidak perlu drill-down manual lewat Pengajar -> Student
     * satu-satu cuma untuk melihat roster yang sudah ada.
     *
     * Diambil dari baris JadwalKelas yang masih aktif saja (bukan
     * seluruh histori sesi -- kelas_count di kolom Statistik bisa jauh
     * lebih besar untuk kelas rutin yang sudah lama jalan), lalu
     * dedup ke kombinasi unik (pengajar, murid, ruangan) per Mata
     * Pelajaran -- satu baris di modal mewakili satu penugasan yang
     * sedang berjalan, bukan satu baris per sesi/tanggal.
     *
     * Satu query untuk semua baris di halaman ini (bukan query per
     * baris di dalam loop) supaya tidak N+1 walau paginate menampilkan
     * 15 Mata Pelajaran sekaligus.
     */
    private function attachRoster(LengthAwarePaginator $mataPelajarans, Company $company): void
    {
        $mataPelajaranIds = $mataPelajarans->pluck('id');

        if ($mataPelajaranIds->isEmpty()) {
            return;
        }

        $rosterByMataPelajaran = JadwalKelas::where('company_id', $company->id)
            ->whereIn('jadwal_mata_pelajaran_id', $mataPelajaranIds)
            ->where('status', JadwalKelas::STATUS_ACTIVE)
            // "Slot kosong" (student_id null, lihat docblock
            // App\Models\JadwalKelas) sengaja DIKELUARKAN dari roster
            // ini -- roster berarti "penugasan yang sedang berjalan",
            // slot kosong belum jadi penugasan apa pun.
            ->whereNotNull('student_id')
            ->with(['pengajar:id,name', 'student:id,name', 'ruangan:id,name'])
            ->get()
            // Kunci dedup ikut sertakan jadwal_mata_pelajaran_id --
            // tanpa itu, murid yang sama diajar pengajar yang sama di
            // ruangan yang sama untuk DUA Mata Pelajaran berbeda (mis.
            // Piano & Vokal) akan salah kebuang jadi cuma satu baris.
            ->unique(fn (JadwalKelas $kelas) => implode('|', [
                $kelas->jadwal_mata_pelajaran_id,
                $kelas->pengajar_id,
                $kelas->student_id,
                $kelas->jadwal_ruangan_id,
            ]))
            ->groupBy('jadwal_mata_pelajaran_id');

        // Batas tampilan per Mata Pelajaran -- murni jaga-jaga (roster
        // realistisnya sudah kecil, sebesar pengajar_count x
        // student_count di kolom Statistik), bukan pembatas normal.
        $maxRosterRows = 300;

        foreach ($mataPelajarans as $mataPelajaran) {
            $roster = ($rosterByMataPelajaran->get($mataPelajaran->id) ?? collect())
                ->sortBy([['pengajar.name', 'asc'], ['student.name', 'asc']])
                ->values();

            $mataPelajaran->setRelation('roster', $roster->take($maxRosterRows));
            $mataPelajaran->roster_truncated_count = max(0, $roster->count() - $maxRosterRows);
        }
    }

    /**
     * Branch-locked members only ever get their own branch to pick from
     * (no picker at all, effectively forced) — same rule
     * Chat\CategoryPhoneBookController::branchOfficesFor() applies.
     */
    private function branchOfficesFor(Company $company, $context)
    {
        $query = BranchOffice::where('company_id', $company->id);

        if (! $context->isOwner) {
            $query->where('id', $context->branchOffice?->id);
        }

        return $query->orderBy('name')->get(['id', 'name']);
    }

    private function findOrFail($context, string $id): JadwalMataPelajaran
    {
        $query = JadwalMataPelajaran::where('company_id', $context->company->id)
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
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($company, $ignoreId) {
                    $exists = JadwalMataPelajaran::where('company_id', $company->id)
                        ->where('name', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Mata Pelajaran / Bidang dengan nama ini sudah ada.');
                    }
                },
            ],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            // exists: alone only checks the row is real, not that it
            // belongs to THIS company — the closure below closes that
            // gap, same rule as Chat\CategoryPhoneBookController.
            'branch_office_id' => [
                'nullable', 'uuid', 'exists:branch_offices,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! BranchOffice::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Branch office tidak valid.');
                    }
                },
            ],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
