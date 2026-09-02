<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\JadwalBranchSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * "Jam Operasional" (Jadwal v2, CLAUDE.md item #15, spec poin 1) --
 * satu halaman singleton PER BRANCH (upsert satu baris App\Models\
 * JadwalBranchSetting per branch_office_id), diakses lewat tombol
 * "Jam Operasional" di baris Branch (lihat jadwal.branch.index /
 * App\Http\Controllers\Jadwal\JadwalBranchController) -- pola sama
 * seperti JadwalReminderSettingController tapi keyed per branch,
 * bukan per company, karena jam operasional MEMANG beda-beda per
 * lokasi fisik.
 */
class JadwalBranchSettingController extends Controller
{
    use ResolvesCompanyContext;

    public function edit(Request $request, string $branchOfficeId): View
    {
        $context = $this->companyContext($request);

        $branch = $this->ownedBranchOrFail($context, $branchOfficeId);

        $setting = JadwalBranchSetting::where('branch_office_id', $branch->id)->first();

        return view('jadwal.jadwal-branch-setting.edit', compact('branch', 'setting'));
    }

    public function update(Request $request, string $branchOfficeId): RedirectResponse
    {
        $context = $this->companyContext($request);

        $branch = $this->ownedBranchOrFail($context, $branchOfficeId);

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.branch-settings.edit', $branch->id)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        JadwalBranchSetting::updateOrCreate(
            ['branch_office_id' => $branch->id],
            [
                'company_id' => $context->company->id,
                'hari_operasional' => array_values(array_map('intval', $validated['hari_operasional'])),
                'jam_buka' => $validated['jam_buka'],
                'jam_tutup' => $validated['jam_tutup'],
                'jam_istirahat_mulai' => $validated['jam_istirahat_mulai'] ?? null,
                'jam_istirahat_selesai' => $validated['jam_istirahat_selesai'] ?? null,
                'durasi_sesi_default_menit' => $validated['durasi_sesi_default_menit'],
                'sesi_per_bulan_default' => $validated['sesi_per_bulan_default'],
                'status' => $validated['status'] ?? 'active',
            ]
        );

        return redirect()
            ->route('jadwal.branch-settings.edit', $branch->id)
            ->with('success', 'Jam Operasional branch "'.$branch->name.'" berhasil disimpan.');
    }

    private function ownedBranchOrFail($context, string $branchOfficeId): BranchOffice
    {
        if ($context->isLockedToBranch() && $context->branchOffice?->id !== $branchOfficeId) {
            abort(404);
        }

        return BranchOffice::where('company_id', $context->company->id)
            ->where('id', $branchOfficeId)
            ->firstOrFail();
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'hari_operasional' => ['required', 'array', 'min:1'],
            'hari_operasional.*' => ['integer', 'between:0,6'],
            'jam_buka' => ['required', 'date_format:H:i'],
            'jam_tutup' => ['required', 'date_format:H:i', 'after:jam_buka'],
            'jam_istirahat_mulai' => ['nullable', 'date_format:H:i', 'required_with:jam_istirahat_selesai'],
            'jam_istirahat_selesai' => ['nullable', 'date_format:H:i', 'required_with:jam_istirahat_mulai', 'after:jam_istirahat_mulai'],
            'durasi_sesi_default_menit' => ['required', 'integer', 'min:5', 'max:600'],
            // Maks 4 -- minggu ke-5 (kalau ada) sengaja tidak pernah
            // dihitung sebagai sesi reguler, disisakan untuk sesi
            // pengganti (lihat spec Jadwal v2 poin 6 & 8 di CLAUDE.md).
            'sesi_per_bulan_default' => ['required', 'integer', 'min:1', 'max:4'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
    }
}
