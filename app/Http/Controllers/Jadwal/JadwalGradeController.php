<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JadwalKategori;
use App\Models\JadwalGrade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as ValidatorContract;
use Illuminate\View\View;

/**
 * CRUD "Grade" (permintaan user 8 September 2026) -- level BARU di
 * bawah App\Models\JadwalKategori, mis. Kategori "Piano Classic" punya
 * Grade "A" (harga 400rb/bulan), "B" (500rb/bulan), "C" (600rb/bulan --
 * paling mahal). Mirroring PERSIS pola App\Http\Controllers\Jadwal\
 * JadwalKategoriController, cuma parent-nya sekarang Kategori (bukan
 * Mata Pelajaran) -- lihat docblock App\Models\JadwalGrade untuk
 * konteks lengkap kenapa harga/persentase/penugasan-Pengajar pindah ke
 * sini dari Kategori. Diakses lewat tombol "+ Add Grade" di baris index
 * Kategori (jadwal.kategori.index) -- jadwal_kategori_id SELALU wajib
 * ada di sini, sama seperti jadwal_mata_pelajaran_id wajib ada di
 * Kategori.
 */
class JadwalGradeController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View|RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $kategoriId = $request->query('jadwal_kategori_id');

        $kategori = $kategoriId
            ? $this->ownedKategoriOrFail($context, $kategoriId)
            : null;

        if (! $kategori) {
            return redirect()
                ->route('jadwal.mata-pelajaran.index')
                ->with('error', 'Pilih Kategori terlebih dahulu sebelum mengelola Grade.');
        }

        $query = JadwalGrade::where('company_id', $company->id)
            ->where('jadwal_kategori_id', $kategoriId)
            ->withCount('jadwalRutins');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $grades = $query->orderBy('name')->paginate(15)->withQueryString()->onEachSide(1);

        return view('jadwal.jadwal-grade.index', compact('grades', 'kategori', 'kategoriId'));
    }

    public function create(Request $request): View
    {
        $context = $this->companyContext($request);

        $kategoriId = $request->query('jadwal_kategori_id');
        $kategori = $this->ownedKategoriOrFail($context, $kategoriId);

        return view('jadwal.jadwal-grade.create', [
            'grade' => null,
            'kategori' => $kategori,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $kategori = $this->ownedKategoriOrFail($context, $request->input('jadwal_kategori_id'));

        $validator = $this->validator($request, $company);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.grade.create', ['jadwal_kategori_id' => $kategori->id])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        JadwalGrade::create([
            'company_id' => $company->id,
            'jadwal_kategori_id' => $kategori->id,
            'name' => $validated['name'],
            'harga_bulanan' => $validated['harga_bulanan'],
            'persentase_company' => $validated['persentase_company'],
            'persentase_pengajar' => $validated['persentase_pengajar'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.grade.index', ['jadwal_kategori_id' => $kategori->id])
            ->with('success', 'Grade berhasil ditambahkan.');
    }

    public function edit(Request $request, string $id): View
    {
        $context = $this->companyContext($request);

        $grade = $this->findOrFail($context, $id);

        return view('jadwal.jadwal-grade.edit', [
            'grade' => $grade,
            'kategori' => $grade->kategori,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $grade = $this->findOrFail($context, $id);

        $validator = $this->validator($request, $company, $id);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.grade.edit', $id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $grade->update([
            'name' => $validated['name'],
            'harga_bulanan' => $validated['harga_bulanan'],
            'persentase_company' => $validated['persentase_company'],
            'persentase_pengajar' => $validated['persentase_pengajar'],
            'status' => $validated['status'] ?? 'active',
        ]);

        return redirect()
            ->route('jadwal.grade.index', ['jadwal_kategori_id' => $grade->jadwal_kategori_id])
            ->with('success', 'Grade berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);

        $grade = $this->findOrFail($context, $id);
        $kategoriId = $grade->jadwal_kategori_id;

        $grade->delete();

        return redirect()
            ->route('jadwal.grade.index', ['jadwal_kategori_id' => $kategoriId])
            ->with('success', 'Grade berhasil dihapus.');
    }

    private function ownedKategoriOrFail($context, ?string $id): ?JadwalKategori
    {
        if (! $id) {
            return null;
        }

        $query = JadwalKategori::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->whereHas('mataPelajaran', function ($q) use ($context) {
                $q->where('branch_office_id', $context->branchOffice?->id)
                    ->orWhereNull('branch_office_id');
            });
        }

        return $query->first();
    }

    private function findOrFail($context, string $id): JadwalGrade
    {
        return JadwalGrade::where('company_id', $context->company->id)
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
