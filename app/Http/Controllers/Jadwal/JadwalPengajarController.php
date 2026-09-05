<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JadwalKategori;
use App\Models\JadwalPengajarKategori;
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
 * atas permintaan user) — level di antara Kategori dan Student: Branch
 * -> Ruangan -> Jam Operasional -> Mata Pelajaran / Bidang -> Kategori
 * -> **Pengajar** -> Student.
 *
 * Update 3 September 2026 (masih permintaan user, sesi yang sama):
 * Pengajar SEKARANG PUNYA MENU SENDIRI di sidebar (lihat
 * resources/views/layouts/partials/menu.blade.php) — TIDAK cuma
 * dijangkau lewat drill-down "+ Add Pengajar" di index Kategori.
 * Polanya sama seperti App\Http\Controllers\Jadwal\
 * JadwalMataPelajaranController / JadwalStudentController: index()
 * mode GLOBAL (tanpa `jadwal_kategori_id`) menampilkan SEMUA baris
 * company (+ kolom Kategori), mode SCOPED (dengan `jadwal_kategori_id`,
 * datang dari tombol drill-down) memfilter ke satu Kategori & sembunyikan
 * kolom yang jadi redundant. create()/edit() ikut pola locked-vs-free
 * "ina" project's University Album Photo: Kategori terkunci (disabled +
 * hidden input) kalau datang dengan `jadwal_kategori_id` valid di query
 * string, dropdown bebas kalau tidak (termasuk SELALU bebas di edit()).
 *
 * Pengajar (App\Models\JadwalPengajarKategori) = penugasan Pengajar
 * (tetap user perusahaan yang sudah ada, lewat ResolvesCompanyContext::
 * companyTeamMembers()) ke satu Kategori, dengan hari & jam
 * ketersediaannya sendiri untuk Kategori itu. Validasi (mis. pengajar
 * sudah terdaftar di Kategori yang sama) ditampilkan lewat alert error
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

        $kategoriId = $request->query('jadwal_kategori_id');
        $kategori = $kategoriId ? $this->ownedKategoriOrFail($context, $kategoriId) : null;

        if ($kategoriId && ! $kategori) {
            return redirect()
                ->route('jadwal.pengajar.index')
                ->with('error', 'Kategori tidak ditemukan.');
        }

        $query = JadwalPengajarKategori::where('company_id', $company->id)
            ->with(['pengajar:id,name,email', 'kategori.mataPelajaran:id,name,branch_office_id', 'jadwals']);

        if ($kategori) {
            $query->where('jadwal_kategori_id', $kategori->id);
        } elseif ($context->isLockedToBranch()) {
            // Mode global (tanpa Kategori) tapi anggota branch-locked --
            // tetap batasi ke Kategori yang Mata Pelajaran-nya milik
            // branch dia (atau lintas-branch, sama rule seperti
            // JadwalKategoriController::ownedMataPelajaranOrFail()).
            $branchOfficeId = $context->branchOffice?->id;
            $query->whereHas('kategori.mataPelajaran', function ($q) use ($branchOfficeId) {
                $q->where('branch_office_id', $branchOfficeId)->orWhereNull('branch_office_id');
            });
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('pengajar', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));
        }

        $pengajarKategoris = $query->orderBy('created_at')->paginate(15)->withQueryString()->onEachSide(1);

        $this->attachMuridCounts($pengajarKategoris, $company);

        return view('jadwal.jadwal-pengajar.index', [
            'pengajarKategoris' => $pengajarKategoris,
            'kategori' => $kategori,
            'mataPelajaran' => $kategori?->mataPelajaran,
        ]);
    }

    /**
     * Update 4 September 2026 (permintaan user): jumlah MURID per baris
     * Pengajar -- diklik pindah ke jadwal.student.index (badge, sama
     * pola link "Add Student" di index.blade.php). App\Models\
     * JadwalStudent TIDAK menyimpan jadwal_kategori_id (cuma
     * jadwal_mata_pelajaran_id + pengajar_id), jadi "murid dari
     * Kategori ini" didekati dengan pasangan (pengajar_id, Mata
     * Pelajaran milik Kategori itu) -- sama scoping yang sudah dipakai
     * link "Add Student"/"Jadwal Rutin" di tempat lain, bukan filter
     * baru.
     *
     * Satu query dikelompokkan (bukan query per baris di dalam loop)
     * supaya tidak N+1 walau paginate menampilkan 15 baris sekaligus.
     *
     * Refactor 5 September 2026 (permintaan user: "kode makin gemuk,
     * tolong dirapikan") -- query hitungnya sendiri dipindah ke
     * App\Services\Jadwal\JadwalCountsService::activeMuridCountsForPairs()
     * (SATU sumber dipakai bersama menu Mata Pelajaran/Student, lihat
     * docblock class itu). Yang tetap di sini cuma bagian yang memang
     * spesifik ke bentuk data halaman ini: membangun pasangan
     * (pengajar_id, jadwal_mata_pelajaran_id) dari $pengajarKategoris,
     * lalu menempelkan hasilnya balik ke tiap baris.
     */
    private function attachMuridCounts(LengthAwarePaginator $pengajarKategoris, Company $company): void
    {
        $pairs = collect($pengajarKategoris->items())
            ->map(fn (JadwalPengajarKategori $pk) => [
                'pengajar_id' => $pk->pengajar_id,
                'jadwal_mata_pelajaran_id' => $pk->kategori->jadwal_mata_pelajaran_id ?? null,
            ])
            ->filter(fn (array $p) => $p['jadwal_mata_pelajaran_id'])
            ->unique(fn (array $p) => $p['pengajar_id'].'|'.$p['jadwal_mata_pelajaran_id']);

        $counts = $this->countsService->activeMuridCountsForPairs($company->id, $pairs);

        foreach ($pengajarKategoris as $pk) {
            $mpId = $pk->kategori->jadwal_mata_pelajaran_id ?? null;
            $pk->murid_count = $mpId ? (int) ($counts->get($pk->pengajar_id.'|'.$mpId)?->total ?? 0) : 0;
        }
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        $kategoriId = $request->query('jadwal_kategori_id');
        $kategori = $kategoriId ? $this->ownedKategoriOrFail($context, $kategoriId) : null;

        return view('jadwal.jadwal-pengajar.create', [
            'pengajarKategori' => null,
            'kategori' => $kategori,
            'selectedKategoriId' => $kategoriId,
            'mataPelajaran' => $kategori?->mataPelajaran,
        ] + $this->formData($context, $kategori));
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $validator = $this->validator($request, $context);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.pengajar.create', array_filter(['jadwal_kategori_id' => $request->input('jadwal_kategori_id')]))
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        // Divalidasi ulang di sini (bukan cuma exists+company di
        // validator()) supaya branch-lock ke Mata Pelajaran-nya Kategori
        // ikut ditegakkan, sama pola seperti findOrFail() di controller
        // Jadwal lain.
        $kategori = $this->ownedKategoriOrFail($context, $validated['jadwal_kategori_id']);
        abort_if(! $kategori, 404);

        DB::transaction(function () use ($company, $kategori, $validated) {
            $pengajarKategori = JadwalPengajarKategori::create([
                'company_id' => $company->id,
                'jadwal_kategori_id' => $kategori->id,
                'pengajar_id' => $validated['pengajar_id'],
                'status' => $validated['status'] ?? 'active',
            ]);

            $this->syncJadwal($pengajarKategori, $validated['jadwal']);
        });

        return redirect()
            ->route('jadwal.pengajar.index', ['jadwal_kategori_id' => $kategori->id])
            ->with('success', 'Pengajar berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $pengajarKategori = $this->findOrFail($context, $id);
        $kategori = $pengajarKategori->kategori;

        // SENGAJA TIDAK mengunci Kategori di sini (selalu dropdown
        // bebas) -- locking cuma berlaku di create(), sama pola "ina"
        // project's University Album Photo edit() (lihat class docblock).
        return view('jadwal.jadwal-pengajar.edit', [
            'pengajarKategori' => $pengajarKategori,
            'kategori' => null,
            'mataPelajaran' => $kategori->mataPelajaran,
        ] + $this->formData($context, null));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $pengajarKategori = $this->findOrFail($context, $id);

        $validator = $this->validator($request, $context, $pengajarKategori->id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.pengajar.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $kategori = $this->ownedKategoriOrFail($context, $validated['jadwal_kategori_id']);
        abort_if(! $kategori, 404);

        DB::transaction(function () use ($pengajarKategori, $kategori, $validated) {
            $pengajarKategori->update([
                'jadwal_kategori_id' => $kategori->id,
                'pengajar_id' => $validated['pengajar_id'],
                'status' => $validated['status'] ?? 'active',
            ]);

            $this->syncJadwal($pengajarKategori, $validated['jadwal']);
        });

        return redirect()
            ->route('jadwal.pengajar.index', ['jadwal_kategori_id' => $kategori->id])
            ->with('success', 'Pengajar berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $pengajarKategori = $this->findOrFail($context, $id);
        $kategoriId = $pengajarKategori->jadwal_kategori_id;

        // AMAN dihapus -- Jadwal Rutin/sesi yang sudah dibuat pengajar
        // ini tidak ikut terhapus (referensi ke users.id langsung, tidak
        // FK ke baris ini, lihat docblock App\Models\
        // JadwalPengajarKategori).
        $pengajarKategori->delete();

        return redirect()
            ->route('jadwal.pengajar.index', ['jadwal_kategori_id' => $kategoriId])
            ->with('success', 'Pengajar berhasil dihapus dari Kategori ini.');
    }

    /**
     * @param  JadwalKategori|null  $kategori  Kalau null, form menampilkan
     *      dropdown Kategori bebas (lihat class docblock) -- daftar
     *      `kategoris` di bawah cuma dihitung kalau memang dibutuhkan.
     */
    private function formData($context, ?JadwalKategori $kategori): array
    {
        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $kategori?->mataPelajaran?->branch_office_id;

        return [
            'teamMembers' => $this->companyTeamMembers($context->company, $branchOfficeId),
            'kategoris' => $kategori ? collect() : JadwalKategori::with('mataPelajaran:id,name,branch_office_id')
                ->where('company_id', $context->company->id)
                ->where('status', 'active')
                ->when($context->isLockedToBranch(), function ($q) use ($context) {
                    $branchOfficeId = $context->branchOffice?->id;
                    $q->whereHas('mataPelajaran', function ($qq) use ($branchOfficeId) {
                        $qq->where('branch_office_id', $branchOfficeId)->orWhereNull('branch_office_id');
                    });
                })
                ->orderBy('name')
                ->get(),
        ];
    }

    private function ownedKategoriOrFail($context, ?string $id): ?JadwalKategori
    {
        if (! $id) {
            return null;
        }

        $kategori = JadwalKategori::with('mataPelajaran')
            ->where('company_id', $context->company->id)
            ->where('id', $id)
            ->first();

        if ($kategori && $context->isLockedToBranch()) {
            $branchOfficeId = $kategori->mataPelajaran?->branch_office_id;

            if ($branchOfficeId && $branchOfficeId !== $context->branchOffice?->id) {
                return null;
            }
        }

        return $kategori;
    }

    private function findOrFail($context, string $id): JadwalPengajarKategori
    {
        return JadwalPengajarKategori::with(['kategori.mataPelajaran', 'jadwals'])
            ->where('company_id', $context->company->id)
            ->where('id', $id)
            ->firstOrFail();
    }

    /**
     * Ganti seluruh slot jadwal (App\Models\JadwalPengajarJadwal) milik
     * satu penugasan Pengajar dengan `$rows` -- hapus semua baris lama,
     * buat ulang dari input yang baru. Lebih sederhana & aman daripada
     * diff per-baris (jumlah baris bisa berubah bebas: tambah/hapus
     * dari form "Tambah Baris" di UI), dan aman dipanggil untuk
     * penugasan yang baru dibuat (jadwals() kosong, delete() no-op).
     *
     * @param  array<int, array{hari: int|string, jam_mulai: string, jam_selesai: string}>  $rows
     */
    private function syncJadwal(JadwalPengajarKategori $pengajarKategori, array $rows): void
    {
        $pengajarKategori->jadwals()->delete();

        foreach ($rows as $row) {
            $pengajarKategori->jadwals()->create([
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
            'jadwal_kategori_id' => [
                'required', 'uuid', 'exists:jadwal_kategori,id',
                function ($attribute, $value, $fail) use ($company) {
                    if ($value && ! JadwalKategori::where('company_id', $company->id)->where('id', $value)->exists()) {
                        $fail('Kategori tidak valid.');
                    }
                },
            ],
            'pengajar_id' => [
                'required', 'uuid', 'exists:users,id',
                function ($attribute, $value, $fail) use ($company, $request, $ignoreId) {
                    $kategoriId = $request->input('jadwal_kategori_id');

                    $exists = JadwalPengajarKategori::where('company_id', $company->id)
                        ->where('jadwal_kategori_id', $kategoriId)
                        ->where('pengajar_id', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Pengajar ini sudah terdaftar di Kategori ini.');
                    }
                },
            ],
            // Ketersediaan sekarang berupa BANYAK slot (hari + jam),
            // bukan satu hari_bisa[] + satu jam_mulai/jam_selesai yang
            // berlaku sama ke semua hari -- lihat App\Models\
            // JadwalPengajarJadwal & class docblock. Satu hari BOLEH
            // muncul lebih dari sekali (mis. Senin 10-12 dan Senin
            // 17-19), jadi TIDAK ada rule unique di sini, cuma
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
