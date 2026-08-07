<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\JadwalKelas;
use App\Models\JadwalKelasSesi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Ketika nama pengajar diklik, keluarkan semua jadwal pengajar
 * tersebut" — clicking a guru's name anywhere in the Jadwal module
 * (currently: Jadwal Kelas index) lands here: every recurring class this
 * one guru teaches across the whole company (not just one cabang, since
 * one guru can teach at several branches — see JadwalKelas's own
 * docblock), plus their upcoming dated sesi, and the quick actions that
 * live on this page (majukan/mundurkan jam, tandai sakit & cari
 * pengganti) — this is the "core" page Jadwal Kelas's own action buttons
 * route back into.
 */
class JadwalGuruController extends Controller
{
    use ResolvesCompanyContext;

    public function show(Request $request, string $guruUserId): View
    {
        $context = $this->companyContext($request);

        // The clicked name always comes from a JadwalKelas row already
        // scoped to this company (see jadwal-kelas/index.blade.php), but
        // this is still a raw URL segment a user could edit by hand — so
        // re-verify the target actually teaches at least one class in
        // THIS company before showing anything about them, same
        // "re-check server-side, don't trust the click" posture as
        // every other findOrFail() in this module.
        $guru = User::whereIn('id', JadwalKelas::where('company_id', $context->company->id)->pluck('guru_user_id'))
            ->where('id', $guruUserId)
            ->firstOrFail();

        $classesQuery = JadwalKelas::where('company_id', $context->company->id)
            ->where('guru_user_id', $guru->id)
            ->with(['mataPelajaran:id,name', 'branchOffice:id,name'])
            ->withCount(['murid' => fn ($q) => $q->where('status', 'active')]);

        if ($context->isLockedToBranch()) {
            $classesQuery->where('branch_office_id', $context->branchOffice?->id);
        }

        $classes = $classesQuery->orderBy('hari')->orderBy('jam_mulai')->get();

        $sesi = JadwalKelasSesi::whereIn('jadwal_kelas_id', $classes->pluck('id'))
            ->where('tanggal', '>=', now()->toDateString())
            ->whereIn('status', ['terjadwal', 'dipindah'])
            ->with(['jadwalKelas:id,name,mata_pelajaran_id,branch_office_id,jam_mulai,jam_selesai', 'jadwalKelas.mataPelajaran:id,name', 'guruPengganti:id,name'])
            ->withCount('muridSesi')
            ->orderBy('tanggal')
            ->limit(30)
            ->get();

        return view('jadwal.guru.show', [
            'guru' => $guru,
            'classes' => $classes,
            'sesi' => $sesi,
        ]);
    }
}
