<?php

namespace App\Http\Controllers\Jadwal;

use App\Exports\Jadwal\JadwalLaporanExport;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Services\Jadwal\JadwalLaporanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan Jadwal v2 (CLAUDE.md item #15 spec poin 12 & 13) -- SATU
 * halaman dengan filter rentang tanggal (dari-sampai), gantikan dua
 * menu terpisah "Laporan Harian" & "Laporan Bulanan" yang sebelumnya
 * ada (satu hari = dari==sampai, satu bulan = dari awal bulan sampai
 * akhir bulan, atau rentang bebas lainnya -- semua ditangani query
 * yang sama di App\Services\Jadwal\JadwalLaporanService). Read-only
 * (tampilan + tombol export Excel), datanya dari situ juga supaya
 * angka yang ditampilkan di layar SAMA PERSIS dengan yang di-export.
 *
 * export() WAJIB dapat dari+sampai yang valid -- kalau tidak (langsung
 * diakses tanpa isi tanggal, atau tanggal dikosongkan manual di URL),
 * redirect balik ke index() dengan flash 'error' "Silakan pilih
 * tanggal terlebih dahulu." (lihat parseRange()). Filter tanggal di
 * halaman index() juga sudah `required` di HTML supaya submit form
 * tidak bisa kosong dari browser -- validasi server ini jaring
 * pengaman kalau HTML itu di-bypass (akses URL export langsung).
 */
class JadwalLaporanController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(protected JadwalLaporanService $laporan)
    {
    }

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $request->query('branch_office_id');

        [$dari, $sampai] = $this->parseRange($request->query('dari'), $request->query('sampai'));

        // Load pertama (belum ada query string sama sekali) default ke
        // hari ini supaya halaman tidak kosong -- tetap terhitung
        // "sudah pilih tanggal" (bukan kosong), jadi tombol Export
        // langsung bisa dipakai. Begini juga field tanggal `dari`/`sampai`
        // TIDAK PERNAH kosong dari sisi kode: admin harus sengaja
        // mengosongkan sendiri (atau akses URL manual) supaya validasi
        // "silakan pilih tanggal" di export() ke-trigger.
        if (! $request->has('dari') && ! $request->has('sampai')) {
            $dari = now()->startOfDay();
            $sampai = now()->startOfDay();
        }

        $sesi = collect();
        $rekap = null;

        if ($dari && $sampai && $sampai->gte($dari)) {
            $sesi = $this->laporan->sesiUntukRentang($company->id, $branchOfficeId ?: null, $dari->copy()->startOfDay(), $sampai->copy()->endOfDay());
            $rekap = $this->laporan->rekap($company->id, $branchOfficeId ?: null, $dari->copy()->startOfDay(), $sampai->copy()->endOfDay());
        }

        $branchOffices = $context->isLockedToBranch()
            ? collect()
            : BranchOffice::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);

        return view('jadwal.laporan.index', [
            'sesi' => $sesi,
            'rekap' => $rekap,
            'dari' => $dari,
            'sampai' => $sampai,
            'branchOfficeId' => $branchOfficeId,
            'branchOffices' => $branchOffices,
        ]);
    }

    public function export(Request $request): BinaryFileResponse|RedirectResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $request->query('branch_office_id');

        [$dari, $sampai] = $this->parseRange($request->query('dari'), $request->query('sampai'));

        if (! $dari || ! $sampai) {
            return redirect()
                ->route('jadwal.laporan.index', array_filter(['branch_office_id' => $branchOfficeId]))
                ->with('error', 'Silakan pilih tanggal terlebih dahulu.');
        }

        if ($sampai->lt($dari)) {
            return redirect()
                ->route('jadwal.laporan.index', array_filter([
                    'dari' => $dari->format('Y-m-d'),
                    'sampai' => $sampai->format('Y-m-d'),
                    'branch_office_id' => $branchOfficeId,
                ]))
                ->with('error', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        }

        $fromOfDay = $dari->copy()->startOfDay();
        $toOfDay = $sampai->copy()->endOfDay();

        $sesi = $this->laporan->sesiUntukRentang($company->id, $branchOfficeId ?: null, $fromOfDay, $toOfDay);
        $rekap = $this->laporan->rekap($company->id, $branchOfficeId ?: null, $fromOfDay, $toOfDay);

        $rangeLabel = $dari->isSameDay($sampai)
            ? $dari->translatedFormat('d F Y')
            : $dari->translatedFormat('d M Y').' - '.$sampai->translatedFormat('d M Y');

        $filename = 'laporan-jadwal-'.$company->slug.'-'.$dari->format('Ymd').'-'.$sampai->format('Ymd').'.xlsx';

        return Excel::download(new JadwalLaporanExport($sesi, $rekap, $rangeLabel), $filename);
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function parseRange(?string $rawDari, ?string $rawSampai): array
    {
        return [$this->parseDate($rawDari), $this->parseDate($rawSampai)];
    }

    private function parseDate(?string $raw): ?Carbon
    {
        if (! $raw) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
