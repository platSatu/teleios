<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\JadwalStudent;
use App\Models\TagihanCategory;
use App\Services\Jadwal\StudentTagihanLink;
use App\Services\PackageLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Popup "Daftarkan Tagihan" di index Student (7 Oktober 2026) -- lihat
 * App\Services\Jadwal\StudentTagihanLink. Semua dicek di server: Student &
 * Kategori milik company ini, cabang Kategori HARUS = cabang Student (tidak
 * bisa dipindah lewat form), user punya akses menu Tagihan, dan cabangnya
 * punya paket Tagihan aktif.
 */
class JadwalStudentTagihanController extends Controller
{
    use ResolvesCompanyContext;

    public function store(Request $request, string $id, StudentTagihanLink $link, PackageLimitService $packages): RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $student = JadwalStudent::where('company_id', $company->id)
            ->when($context->isLockedToBranch(), fn ($q) => $q->where('branch_office_id', $context->branchOffice?->id))
            ->with('branchOffice')
            ->findOrFail($id);

        $back = fn (string $message) => back()->with('error', $message);

        if (! $context->canAccessRoute('tagihan.pelanggan.index')) {
            return $back('Anda tidak memiliki akses ke menu Tagihan.');
        }

        if ($student->status !== JadwalStudent::STATUS_ACTIVE) {
            return $back('Student nonaktif tidak bisa didaftarkan ke Tagihan.');
        }

        if (! $student->branchOffice) {
            return $back('Student ini belum punya Branch. Atur Branch-nya dulu lewat Edit Student.');
        }

        if (! $packages->hasActiveCategoryPackage($company, ['Tagihan', 'Pembayaran'], $student->branchOffice)) {
            return $back("Branch {$student->branchOffice->name} belum punya paket Tagihan aktif.");
        }

        $validated = $request->validate([
            'tagihan_category_id' => ['required', 'uuid'],
            'nominal' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ], [], ['tagihan_category_id' => 'Kategori Tagihan', 'nominal' => 'Nominal per bulan']);

        // Kategori wajib milik company DAN cabang Student ini.
        $category = TagihanCategory::where('company_id', $company->id)
            ->where('branch_office_id', $student->branch_office_id)
            ->find($validated['tagihan_category_id']);

        if (! $category) {
            return $back('Kategori Tagihan tidak valid untuk Branch Student ini.');
        }

        $link->register($student, $category, (int) $validated['nominal'], $request->boolean('kirim_link_otomatis'));

        return back()->with('success', "{$student->name} terdaftar di Tagihan \"{$category->name}\" (Rp ".number_format((int) $validated['nominal'], 0, ',', '.').' / bulan).');
    }
}
