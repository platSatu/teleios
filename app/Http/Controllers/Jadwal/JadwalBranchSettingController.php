<?php

namespace App\Http\Controllers\Jadwal;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\JadwalBranchSetting;
use App\Models\JadwalRuangan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * "Jam Operasional" (Jadwal v2, CLAUDE.md item #15, spec poin 1) --
 * datanya TETAP satu baris App\Models\JadwalBranchSetting per
 * branch_office_id (upsert, lihat update() di bawah) -- ini SENGAJA
 * tidak diubah jadi per-Ruangan supaya App\Console\Commands\
 * GenerateJadwalRutinSesi, validasi bentrok
 * (JadwalRutinConflictService) & App\Models\JadwalKategori::
 * hargaPerSesi() yang semuanya baca `branchOffice->jadwalBranchSetting`
 * sebagai satu baris per branch TETAP jalan tanpa berubah.
 *
 * Yang berubah (restrukturisasi drill-down 14 September 2026, atas
 * permintaan user): TITIK MASUKnya, dari tombol di baris Branch
 * (jadwal.branch.index) pindah ke tombol "Add Jam Operasional" di
 * baris Ruangan (jadwal.ruangan.index) -- lihat index() di bawah.
 * Karena datanya tetap per-branch (bukan per-ruangan), mengedit dari
 * Ruangan A akan mengubah Jam Operasional yang SAMA dengan Ruangan B
 * kalau satu branch (lihat catatan di index.blade.php & edit.blade.php).
 * index()/destroy() baru, edit()/update() isinya TIDAK berubah dari
 * sebelumnya (masih pola sama seperti JadwalReminderSettingController,
 * keyed per branch bukan per company) selain membawa `ruangan_id`
 * supaya redirect balik ke Ruangan yang sama.
 */
class JadwalBranchSettingController extends Controller
{
    use ResolvesCompanyContext;

    /**
     * Titik masuk baru (lihat class docblock) -- diakses lewat tombol
     * "Add Jam Operasional" di baris Ruangan, `ruangan_id` WAJIB ada di
     * query string supaya tahu branch mana yang dituju & supaya
     * tombol "Kembali"/"Simpan" bisa balik ke Ruangan yang sama.
     * Menampilkan 0 atau 1 baris (branch belum/sudah punya setting)
     * dengan tombol Tambah/Edit/Hapus -- bukan literal list banyak
     * baris, karena datanya memang tetap singleton per branch.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $ruanganId = $request->query('ruangan_id');

        $ruangan = $ruanganId
            ? JadwalRuangan::where('company_id', $company->id)->where('id', $ruanganId)->first()
            : null;

        if (! $ruangan) {
            return redirect()
                ->route('jadwal.ruangan.index')
                ->with('error', 'Pilih Ruangan terlebih dahulu sebelum mengelola Jam Operasional.');
        }

        if ($context->isLockedToBranch() && $context->branchOffice?->id !== $ruangan->branch_office_id) {
            abort(404);
        }

        $branch = $ruangan->branchOffice;
        $setting = JadwalBranchSetting::where('branch_office_id', $branch->id)->first();

        return view('jadwal.jadwal-branch-setting.index', compact('ruangan', 'branch', 'setting'));
    }

    public function edit(Request $request, string $branchOfficeId): View
    {
        $context = $this->companyContext($request);

        $branch = $this->ownedBranchOrFail($context, $branchOfficeId);

        $setting = JadwalBranchSetting::where('branch_office_id', $branch->id)->first();
        $ruanganId = $request->query('ruangan_id');

        return view('jadwal.jadwal-branch-setting.edit', compact('branch', 'setting', 'ruanganId'));
    }

    public function update(Request $request, string $branchOfficeId): RedirectResponse
    {
        $context = $this->companyContext($request);

        $branch = $this->ownedBranchOrFail($context, $branchOfficeId);
        $ruanganId = $request->input('ruangan_id');

        $validator = $this->validator($request);

        if ($validator->fails()) {
            return redirect()
                ->route('jadwal.branch-settings.edit', array_filter(['branchOfficeId' => $branch->id, 'ruangan_id' => $ruanganId]))
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
            ->route('jadwal.branch-settings.index', array_filter(['ruangan_id' => $ruanganId]))
            ->with('success', 'Jam Operasional branch "'.$branch->name.'" berhasil disimpan.');
    }

    /**
     * Hapus Jam Operasional branch ini (kembali ke status "belum
     * diatur"). AMAN dari sisi generator -- GenerateJadwalRutinSesi
     * sudah menangani branch tanpa Jam Operasional dengan skip +
     * warning log (lihat docblock class itu), bukan error/crash.
     * `ruangan_id` dibawa dari form hidden input supaya redirect balik
     * ke Ruangan yang sama.
     */
    public function destroy(Request $request, string $branchOfficeId): RedirectResponse
    {
        $context = $this->companyContext($request);

        $branch = $this->ownedBranchOrFail($context, $branchOfficeId);
        $ruanganId = $request->input('ruangan_id');

        JadwalBranchSetting::where('branch_office_id', $branch->id)->delete();

        return redirect()
            ->route('jadwal.branch-settings.index', array_filter(['ruangan_id' => $ruanganId]))
            ->with('success', 'Jam Operasional branch "'.$branch->name.'" berhasil dihapus.');
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
