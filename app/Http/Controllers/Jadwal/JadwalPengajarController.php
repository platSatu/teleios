<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JadwalKategori;
use App\Models\JadwalPengajarKategori;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;
use Illuminate\View\View;

/**
 * CRUD "Pengajar" (restrukturisasi drill-down Jadwal 14 September 2026,
 * atas permintaan user) — level BARU di antara Kategori dan Student:
 * Branch -> Ruangan -> Jam Operasional -> Mata Pelajaran / Bidang ->
 * Kategori -> **Pengajar** -> Student.
 *
 * SEBELUMNYA controller ini cuma index() read-only (pilih dari user
 * perusahaan yang sudah ada, tanpa tabel sendiri, scoped ke Mata
 * Pelajaran / Bidang langsung). SEKARANG full CRUD atas App\Models\
 * JadwalPengajarKategori — penugasan Pengajar (tetap dari user
 * perusahaan yang sama, lihat ResolvesCompanyContext::
 * companyTeamMembers()) ke satu Kategori, dengan hari & jam
 * ketersediaannya sendiri untuk Kategori itu (form "hari yang bisa" +
 * "jam ngajar dari - sampai"). Selalu diakses lewat tombol "+ Add
 * Pengajar" di index Kategori (lihat JadwalKategoriController::index()),
 * jadi `jadwal_kategori_id` WAJIB ada di query string.
 *
 * Ketersediaan di sini MURNI INFO ditampilkan di form Add Student
 * (lihat JadwalStudentController::create()) — TIDAK divalidasi silang
 * ke App\Models\JadwalRutin, validasi bentrok jadwal tetap sepenuhnya
 * di App\Services\Jadwal\JadwalRutinConflictService seperti sebelumnya.
 */
class JadwalPengajarController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View|RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $kategoriId = $request->query('jadwal_kategori_id');

        $kategori = $this->ownedKategoriOrFail($context, $kategoriId);

        if (! $kategori) {
            return redirect()
                ->route('jadwal.mata-pelajaran.index')
                ->with('error', 'Pilih Kategori terlebih dahulu sebelum mengelola Pengajar.');
        }

        $query = JadwalPengajarKategori::where('company_id', $company->id)
            ->where('jadwal_kategori_id', $kategori->id)
            ->with('pengajar:id,name,email');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->whereHas('pengajar', fn ($q) => $q->where('name', 'like', '%'.$search.'%'));
        }

        $pengajarKategoris = $query->orderBy('created_at')->paginate(15)->withQueryString()->onEachSide(1);

        return view('jadwal.jadwal-pengajar.index', [
            'pengajarKategoris' => $pengajarKategoris,
            'kategori' => $kategori,
            'mataPelajaran' => $kategori->mataPelajaran,
        ]);
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        $kategori = $this->ownedKategoriOrFail($context, $request->query('jadwal_kategori_id'));

        abort_if(! $kategori, 404);

        return view('jadwal.jadwal-pengajar.create', [
            'pengajarKategori' => null,
            'kategori' => $kategori,
            'mataPelajaran' => $kategori->mataPelajaran,
        ] + $this->formData($context, $kategori));
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $kategori = $this->ownedKategoriOrFail($context, $request->input('jadwal_kategori_id'));

        abort_if(! $kategori, 404);

        $validator = $this->validator($request, $company, $kategori);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.pengajar.create', ['jadwal_kategori_id' => $kategori->id])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        JadwalPengajarKategori::create([
            'company_id' => $company->id,
            'jadwal_kategori_id' => $kategori->id,
            'pengajar_id' => $validated['pengajar_id'],
            'hari_bisa' => array_values(array_map('intval', $validated['hari_bisa'])),
            'jam_mulai' => $validated['jam_mulai'],
            'jam_selesai' => $validated['jam_selesai'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.pengajar.index', ['jadwal_kategori_id' => $kategori->id])
            ->with('success', 'Pengajar berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $pengajarKategori = $this->findOrFail($context, $id);
        $kategori = $pengajarKategori->kategori;

        return view('jadwal.jadwal-pengajar.edit', [
            'pengajarKategori' => $pengajarKategori,
            'kategori' => $kategori,
            'mataPelajaran' => $kategori->mataPelajaran,
        ] + $this->formData($context, $kategori));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $pengajarKategori = $this->findOrFail($context, $id);
        $kategori = $pengajarKategori->kategori;

        $validator = $this->validator($request, $company, $kategori, $pengajarKategori->id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.pengajar.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $pengajarKategori->update([
            'pengajar_id' => $validated['pengajar_id'],
            'hari_bisa' => array_values(array_map('intval', $validated['hari_bisa'])),
            'jam_mulai' => $validated['jam_mulai'],
            'jam_selesai' => $validated['jam_selesai'],
            'status' => $validated['status'] ?? 'active',
        ]);

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

    private function formData($context, JadwalKategori $kategori): array
    {
        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $kategori->mataPelajaran?->branch_office_id;

        return [
            'teamMembers' => $this->companyTeamMembers($context->company, $branchOfficeId),
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
        return JadwalPengajarKategori::with('kategori.mataPelajaran')
            ->where('company_id', $context->company->id)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function validator(Request $request, Company $company, JadwalKategori $kategori, ?string $ignoreId = null): ValidatorContract
    {
        return Validator::make($request->all(), [
            'pengajar_id' => [
                'required', 'uuid', 'exists:users,id',
                function ($attribute, $value, $fail) use ($company, $kategori, $ignoreId) {
                    $exists = JadwalPengajarKategori::where('company_id', $company->id)
                        ->where('jadwal_kategori_id', $kategori->id)
                        ->where('pengajar_id', $value)
                        ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                        ->exists();

                    if ($exists) {
                        $fail('Pengajar ini sudah terdaftar di Kategori ini.');
                    }
                },
            ],
            'hari_bisa' => ['required', 'array', 'min:1'],
            'hari_bisa.*' => ['integer', 'between:0,6'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
