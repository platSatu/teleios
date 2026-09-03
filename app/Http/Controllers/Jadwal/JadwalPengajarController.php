<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\JadwalMataPelajaran;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Tingkat ke-3 drill-down Jadwal (Branch -> Mata Pelajaran / Bidang ->
 * Pengajar -> Student -> Jadwal). Sengaja READ-ONLY (index saja) dan
 * TIDAK punya tabel sendiri — Pengajar tetap dipilih dari user
 * perusahaan yang rolenya ditandai is_pengajar, lewat App\Http\
 * Controllers\Concerns\ResolvesCompanyContext::companyPengajarMembers()
 * (lihat App\Http\Controllers\Jadwal\JadwalKelasController, yang pakai
 * method yang sama). Halaman ini cuma pintu masuk untuk memilih pengajar
 * mana yang jadi konteks saat membuat Student baru lewat tombol
 * "+ Add Student" di setiap baris.
 *
 * Selalu diakses lewat tombol "+ Add Pengajar" di index Mata Pelajaran
 * / Bidang (lihat JadwalMataPelajaranController::index()), jadi
 * `jadwal_mata_pelajaran_id` WAJIB ada di query string — tanpa itu tidak
 * ada konteks apa pun untuk dibawa ke Student, jadi diarahkan balik ke
 * index Mata Pelajaran / Bidang dengan pesan error.
 */
class JadwalPengajarController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View|RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $mataPelajaranId = $request->query('jadwal_mata_pelajaran_id');

        $mataPelajaran = $mataPelajaranId
            ? JadwalMataPelajaran::where('company_id', $company->id)->where('id', $mataPelajaranId)->first()
            : null;

        if (! $mataPelajaran) {
            return redirect()
                ->route('jadwal.mata-pelajaran.index')
                ->with('error', 'Pilih Mata Pelajaran / Bidang terlebih dahulu sebelum memilih Pengajar.');
        }

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $mataPelajaran->branch_office_id;

        $teamMembers = $this->companyPengajarMembers($company, $branchOfficeId);

        if ($request->filled('search')) {
            $search = $request->string('search');
            $teamMembers = $teamMembers->filter(
                fn ($member) => str_contains(mb_strtolower($member->name), mb_strtolower($search))
            )->values();
        }

        return view('jadwal.jadwal-pengajar.index', [
            'teamMembers' => $teamMembers,
            'mataPelajaran' => $mataPelajaran,
        ]);
    }
}
