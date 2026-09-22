<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\LedgerEntry;
use App\Services\Wallet\WalletProvisioningService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Dashboard Saldo" -- level BRANCH & COMPANY sekaligus, tergantung
 * App\Services\Company\CompanyContext caller:
 *  - Staff yang locked ke satu branch (isLockedToBranch()): cuma
 *    lihat saldo & histori branch-nya sendiri, tanpa kartu ringkasan
 *    branch lain ataupun total company.
 *  - Owner / role company-wide (seesAllBranches()): lihat kartu
 *    "Total Saldo Semua Branch" (rollup, BUKAN Wallet tersendiri --
 *    lihat App\Services\Wallet\WalletProvisioningService, level
 *    "Company" di skema ini memang cuma agregat dari tiap Wallet
 *    BranchOffice, sesuai diskusi 22 September 2026) plus kartu
 *    per-branch, dan bisa pilih satu branch untuk drill-down histori
 *    lengkapnya (?branch_office_id=...).
 *
 * Bagian 6/6 rencana fitur disbursement Duitku. Wallet dibuat
 * get-or-create lewat WalletProvisioningService::forBranch() supaya
 * kartu ringkasan tetap tampil (saldo Rp 0) untuk branch yang belum
 * pernah menerima pembayaran Tagihan sama sekali.
 */
class SaldoDashboardController extends Controller
{
    use ResolvesCompanyContext;

    public function index(Request $request): View
    {
        $context = $this->companyContext($request);
        $company = $context->company;

        $branches = $context->isLockedToBranch()
            ? collect([$context->branchOffice])->filter()
            : $company->branchOffices()->orderBy('name')->get();

        $wallets = $branches->mapWithKeys(
            fn (BranchOffice $branch) => [$branch->id => WalletProvisioningService::forBranch($branch->id)]
        );

        $totalSaldo = $wallets->sum(fn ($w) => (float) $w->balance);

        $selectedBranchId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : ($request->query('branch_office_id') ?: $branches->first()?->id);

        $selectedBranch = $branches->firstWhere('id', $selectedBranchId);
        $selectedWallet = $selectedBranch ? $wallets->get($selectedBranch->id) : null;

        $histori = $selectedWallet
            ? LedgerEntry::with('transaction')
                ->where('wallet_id', $selectedWallet->id)
                ->latest()
                ->paginate(20)
                ->withQueryString()
            : null;

        return view('keuangan.dashboard.index', compact(
            'context', 'branches', 'wallets', 'totalSaldo', 'selectedBranch', 'selectedWallet', 'histori'
        ));
    }
}
