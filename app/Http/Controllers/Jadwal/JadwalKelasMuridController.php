<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\JadwalKelas;
use App\Models\JadwalKelasMurid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Enroll/unenroll actions nested under one JadwalKelas's show() page —
 * this never gets its own index, the roster only ever makes sense in
 * the context of one specific class (see Jadwal\JadwalKelasController::
 * show()).
 */
class JadwalKelasMuridController extends Controller
{
    use ResolvesCompanyContext;

    public function store(Request $request, string $jadwalKelasId): RedirectResponse
    {
        $context = $this->companyContext($request);
        $jadwalKelas = $this->findJadwalKelasOrFail($context, $jadwalKelasId);

        $validator = Validator::make($request->all(), [
            'murid_user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.jadwal-kelas.show', $jadwalKelas->id)
                ->withErrors($validator);
        }

        $muridUserId = $validator->validated()['murid_user_id'];

        $existing = JadwalKelasMurid::where('jadwal_kelas_id', $jadwalKelas->id)
            ->where('murid_user_id', $muridUserId)
            ->first();

        if ($existing) {
            if ($existing->status !== 'active') {
                $existing->update(['status' => 'active', 'joined_at' => now()]);
            }
        } else {
            JadwalKelasMurid::create([
                'jadwal_kelas_id' => $jadwalKelas->id,
                'murid_user_id' => $muridUserId,
                'status' => 'active',
            ]);
        }

        return redirect()
            ->route('jadwal.jadwal-kelas.show', $jadwalKelas->id)
            ->with('success', 'Murid berhasil didaftarkan ke jadwal kelas ini.');
    }

    public function destroy(Request $request, string $jadwalKelasId, string $id): RedirectResponse
    {
        $context = $this->companyContext($request);
        $jadwalKelas = $this->findJadwalKelasOrFail($context, $jadwalKelasId);

        $enrollment = JadwalKelasMurid::where('jadwal_kelas_id', $jadwalKelas->id)
            ->where('id', $id)
            ->firstOrFail();

        // Soft withdrawal, not a hard delete — keeps historical
        // jadwal_kelas_sesi_murid attendance records intact (cascade
        // delete on this row would wipe them, losing exactly the
        // attendance history this whole feature exists to keep).
        $enrollment->update(['status' => 'berhenti']);

        return redirect()
            ->route('jadwal.jadwal-kelas.show', $jadwalKelas->id)
            ->with('success', 'Murid dikeluarkan dari jadwal kelas ini.');
    }

    private function findJadwalKelasOrFail($context, string $id): JadwalKelas
    {
        $query = JadwalKelas::where('company_id', $context->company->id)->where('id', $id);

        if ($context->isLockedToBranch()) {
            $query->where('branch_office_id', $context->branchOffice?->id);
        }

        return $query->firstOrFail();
    }
}
