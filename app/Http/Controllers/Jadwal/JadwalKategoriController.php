<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JadwalKategori;
use App\Models\JadwalMataPelajaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;
use Illuminate\View\View;

/**
 * CRUD "Kategori" (Jadwal v2, CLAUDE.md item #15, spec poin 3) -- level
 * BARU di bawah Kelas (App\Models\JadwalMataPelajaran), mis. Kelas
 * "Piano" punya Kategori "Classic Level 1" (harga 400rb/bulan) &
 * "Classic Level 2" (harga 500rb/bulan). Setiap Kategori punya harga
 * BULANAN (admin input harga bulanan, bukan per sesi -- harga per sesi
 * dihitung otomatis dari situ, lihat App\Models\JadwalKategori::
 * hargaPerSesi()) + persentase split company/pengajar SENDIRI (harus
 * berjumlah 100). Diakses lewat tombol "Kategori" di baris index Mata
 * Pelajaran / Bidang (jadwal.mata-pelajaran.index) --
 * jadwal_mata_pelajaran_id SELALU wajib ada di sini (beda dari Ruangan
 * yang wajib branch_office_id), karena Kategori tidak masuk akal
 * berdiri sendiri lintas Kelas.
 */
class JadwalKategoriController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View|RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $mataPelajaranId = $request->query('jadwal_mata_pelajaran_id');

        $mataPelajaran = $mataPelajaranId
            ? $this->ownedMataPelajaranOrFail($context, $mataPelajaranId)
            : null;

        if (! $mataPelajaran) {
            return redirect()
                ->route('jadwal.mata-pelajaran.index')
                ->with('error', 'Pilih Kelas / Mata Pelajaran terlebih dahulu sebelum mengelola Kategori.');
        }

        $query = JadwalKategori::where('company_id', $company->id)
            ->where('jadwal_mata_pelajaran_id', $mataPelajaranId)
            ->withCount('jadwalRutins');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $kategoris = $query->orderBy('name')->paginate(15)->withQueryString()->onEachSide(1);

        return view('jadwal.jadwal-kategori.index', compact('kategoris', 'mataPelajaran', 'mataPelajaranId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        $mataPelajaranId = $request->query('jadwal_mata_pelajaran_id');
        $mataPelajaran = $this->ownedMataPelajaranOrFail($context, $mataPelajaranId);

        return view('jadwal.jadwal-kategori.create', [
            'kategori' => null,
            'mataPelajaran' => $mataPelajaran,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $mataPelajaran = $this->ownedMataPelajaranOrFail($context, $request->input('jadwal_mata_pelajaran_id'));

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.kategori.create', ['jadwal_mata_pelajaran_id' => $mataPelajaran->id])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        JadwalKategori::create([
            'company_id' => $company->id,
            'jadwal_mata_pelajaran_id' => $mataPelajaran->id,
            'name' => $validated['name'],
            'harga_bulanan' => $validated['harga_bulanan'],
            'persentase_company' => $validated['persentase_company'],
            'persentase_pengajar' => $validated['persentase_pengajar'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.kategori.index', ['jadwal_mata_pelajaran_id' => $mataPelajaran->id])
            ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $kategori = $this->findOrFail($context, $id);

        return view('jadwal.jadwal-kategori.edit', [
            'kategori' => $kategori,
            'mataPelajaran' => $kategori->mataPelajaran,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $kategori = $this->findOrFail($context, $id);

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.kategori.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $kategori->update([
            'name' => $validated['name'],
            'harga_bulanan' => $validated['harga_bulanan'],
            'persentase_company' => $validated['persentase_company'],
            'persentase_pengajar' => $validated['persentase_pengajar'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.kategori.index', ['jadwal_mata_pelajaran_id' => $kategori->jadwal_mata_pelajaran_id])
            ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $kategori = $this->findOrFail($context, $id);
        $mataPelajaranId = $kategori->jadwal_mata_pelajaran_id;

        $kategori->delete();

        return redirect()
            ->route('jadwal.kategori.index', ['jadwal_mata_pelajaran_id' => $mataPelajaranId])
            ->with('success', 'Kategori berhasil dihapus.');
    }

    private function ownedMataPelajaranOrFail($context, ?string $id): ?JadwalMataPelajaran
    {
        if (! $id) {
            return null;
        }

        $query = JadwalMataPelajaran::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where(function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        return $query->first();
    }

    private function findOrFail($context, string $id): JadwalKategori
    {
        return JadwalKategori::where('company_id', $context->company->id)
            ->where('id', $id)
            ->firstOrFail();
    }

    private function validator(Request $request, Company $company, ?string $ignoreId = null): ValidatorContract
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'harga_bulanan' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'persentase_company' => ['required', 'numeric', 'min:0', 'max:100'],
            'persentase_pengajar' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $validator->after(function (ValidatorContract $v) use ($request) {
            $companyPct = (float) $request->input('persentase_company');
            $pengajarPct = (float) $request->input('persentase_pengajar');

            if (abs(($companyPct + $pengajarPct) - 100) > 0.01) {
                $v->errors()->add('persentase_pengajar', 'Persentase company + pengajar harus berjumlah 100.');
            }
        });

        return $validator;
    }
}
