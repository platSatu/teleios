<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\PengajarFeeTransfer;
use App\Services\Wallet\PengajarFeeTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Fitur "Transfer Fee" bulanan (diskusi 22 September 2026) -- satu
 * halaman: pilih branch (kalau caller tidak terkunci ke satu branch,
 * lihat App\Services\Company\CompanyContext::isLockedToBranch())
 * + periode bulan, lihat preview breakdown per pengajar, lalu eksekusi.
 * Otorisasi murni lewat scoping company/branch yang sama seperti
 * seluruh controller Tagihan -- seorang admin yang terkunci ke branch X
 * tidak bisa menaikkan periode/branch di luar itu (index()/execute()
 * selalu memaksa branch_office_id balik ke context->branchOffice kalau
 * locked), sedangkan owner company boleh pilih branch mana pun di
 * bawahnya lewat dropdown.
 */
class PengajarFeeTransferController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(protected PengajarFeeTransferService $transferService)
    {
    }

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branches = $context->isLockedToBranch()
            ? collect([$context->branchOffice])
            : $company->branchOffices()->orderBy('name')->get();

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : ($request->query('branch_office_id') ?: $branches->first()?->id);

        $branch = $branches->firstWhere('id', $branchOfficeId);

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $preview = null;

        if ($branch) {
            $preview = $this->transferService->preview($branch, $year, $month);
        }

        $riwayat = $branch
            ? PengajarFeeTransfer::where('branch_office_id', $branch->id)
                ->latest()
                ->limit(12)
                ->get()
            : collect();

        return view('keuangan.transfer-fee.index', [
            'branches' => $branches,
            'branch' => $branch,
            'year' => $year,
            'month' => $month,
            'preview' => $preview,
            'riwayat' => $riwayat,
        ]);
    }

    public function execute(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_office_id' => ['required', 'uuid'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $context = $this->companyContext($request);
        $company = $context->company;

        // Kalau caller terkunci ke satu branch, PAKSA balik ke branch
        // itu -- jangan percaya branch_office_id dari form kalau
        // ternyata beda (mis. hasil manipulasi request manual).
        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $validated['branch_office_id'];

        $branch = BranchOffice::where('company_id', $company->id)
            ->where('id', $branchOfficeId)
            ->firstOrFail();

        try {
            $transfer = $this->transferService->execute($branch, $validated['year'], $validated['month'], $request->user());
        } catch (RuntimeException $e) {
            return redirect()
                ->route('keuangan.transfer-fee.index', [
                    'branch_office_id' => $branch->id,
                    'year' => $validated['year'],
                    'month' => $validated['month'],
                ])
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('keuangan.transfer-fee.index', [
                'branch_office_id' => $branch->id,
                'year' => $validated['year'],
                'month' => $validated['month'],
            ])
            ->with('success', 'Transfer fee berhasil — '.$transfer->pengajar_count.' pengajar, total Rp '.number_format((float) $transfer->total_debited, 0, ',', '.').'.');
    }
}
