<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Titik masuk paling atas dari drill-down Jadwal: Branch -> Mata
 * Pelajaran / Bidang -> Pengajar -> Student -> Jadwal (lihat App\Http\
 * Controllers\Jadwal\JadwalMataPelajaranController's docblock dan
 * seterusnya — semuanya mengikuti pola "ina" project's University ->
 * Album -> Photo).
 *
 * Sengaja READ-ONLY (index saja, tanpa create/edit/delete) — ini BUKAN
 * manajemen branch yang sebenarnya (itu sudah ada di User\Profile\
 * BranchOfficeController, menu "Setting > Branch Office"), cuma pintu
 * masuk untuk memilih branch mana yang jadi konteks awal saat membuat
 * Mata Pelajaran / Bidang baru lewat tombol "+ Add Mata Pelajaran /
 * Bidang" di setiap baris.
 */
class JadwalBranchController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $query = BranchOffice::where('company_id', $company->id)
            ->withCount([
                'jadwalMataPelajarans as jadwal_mata_pelajaran_count',
            ]);

        // Anggota yang terkunci ke satu branch cuma perlu lihat
        // branch-nya sendiri di sini — sama seperti setiap controller
        // Jadwal/Chat lain yang branch-scoped.
        if ($context->isLockedToBranch()) {
            $query->where('id', $context->branchOffice?->id);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $branches = $query->orderBy('name')->paginate(15)->withQueryString()->onEachSide(1);

        return view('jadwal.jadwal-branch.index', compact('branches'));
    }
}
