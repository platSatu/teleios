<?php

namespace App\Http\Controllers\Jadwal;

use App\Exports\Jadwal\JadwalLaporanBulananExport;
use App\Exports\Jadwal\JadwalLaporanHarianExport;
use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Services\Jadwal\JadwalLaporanService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan Harian & Bulanan Jadwal v2 (CLAUDE.md item #15 spec poin 12
 * & 13) -- keduanya read-only (tampilan + tombol export Excel), datanya
 * dari App\Services\Jadwal\JadwalLaporanService supaya angka yang
 * ditampilkan di layar SAMA PERSIS dengan yang di-export.
 */
class JadwalLaporanController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(protected JadwalLaporanService $laporan)
    {
    }

    public function harian(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $request->query('branch_office_id');

        $date = $this->parseDate($request->query('tanggal'));

        $sesi = $this->laporan->harian($company->id, $branchOfficeId ?: null, $date);

        $branchOffices = $context->isLockedToBranch()
            ? collect()
            : BranchOffice::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);

        return view('jadwal.laporan.harian', [
            'sesi' => $sesi,
            'date' => $date,
            'branchOfficeId' => $branchOfficeId,
            'branchOffices' => $branchOffices,
        ]);
    }

    public function harianExport(Request $request): BinaryFileResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $request->query('branch_office_id');

        $date = $this->parseDate($request->query('tanggal'));

        $sesi = $this->laporan->harian($company->id, $branchOfficeId ?: null, $date);

        $filename = 'laporan-harian-jadwal-'.$company->slug.'-'.$date->format('Ymd').'.xlsx';

        return Excel::download(new JadwalLaporanHarianExport($sesi, $date->translatedFormat('d F Y')), $filename);
    }

    public function bulanan(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $request->query('branch_office_id');

        [$monthStart, $monthEnd, $monthLabel] = $this->parseMonth($request->query('bulan'));

        $data = $this->laporan->bulanan($company->id, $branchOfficeId ?: null, $monthStart, $monthEnd);

        $branchOffices = $context->isLockedToBranch()
            ? collect()
            : BranchOffice::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']);

        return view('jadwal.laporan.bulanan', [
            'data' => $data,
            'monthValue' => $monthStart->format('Y-m'),
            'monthLabel' => $monthLabel,
            'branchOfficeId' => $branchOfficeId,
            'branchOffices' => $branchOffices,
        ]);
    }

    public function bulananExport(Request $request): BinaryFileResponse
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $request->query('branch_office_id');

        [$monthStart, $monthEnd, $monthLabel] = $this->parseMonth($request->query('bulan'));

        $data = $this->laporan->bulanan($company->id, $branchOfficeId ?: null, $monthStart, $monthEnd);

        $filename = 'laporan-bulanan-jadwal-'.$company->slug.'-'.$monthStart->format('Ym').'.xlsx';

        return Excel::download(new JadwalLaporanBulananExport($data, $monthLabel), $filename);
    }

    private function parseDate(?string $raw): Carbon
    {
        try {
            return $raw ? Carbon::createFromFormat('Y-m-d', $raw)->startOfDay() : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    private function parseMonth(?string $raw): array
    {
        try {
            $start = $raw ? Carbon::createFromFormat('Y-m-d', $raw.'-01')->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
        }

        return [$start->copy()->startOfDay(), $start->copy()->endOfMonth()->endOfDay(), $start->translatedFormat('F Y')];
    }
}
