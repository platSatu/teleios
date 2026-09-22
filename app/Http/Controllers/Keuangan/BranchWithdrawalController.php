<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Concerns\ResolvesCompanyContext;
use App\Http\Controllers\Controller;
use App\Models\BranchOffice;
use App\Models\WalletWithdrawal;
use App\Services\Payment\DuitkuDisbursementService;
use App\Services\Wallet\WalletProvisioningService;
use App\Services\Wallet\WalletWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * "Tarik Saldo" untuk Wallet milik satu BranchOffice -- sisa saldo
 * setelah "Transfer Fee" bulanan (lihat App\Http\Controllers\Keuangan\
 * PengajarFeeTransferController) ditarik ke rekening bank COMPANY
 * (bukan rekening branch -- keputusan user 22 September 2026: rekening
 * diisi manual tiap kali, tidak ada rekening tersimpan per branch).
 * Sama pola scoping company/branch dengan seluruh controller Keuangan/
 * Tagihan lain.
 */
class BranchWithdrawalController extends Controller
{
    use ResolvesCompanyContext;

    public function __construct(protected WalletWithdrawalService $service)
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
        $wallet = $branch ? WalletProvisioningService::forBranch($branch->id) : null;

        $riwayat = $wallet
            ? WalletWithdrawal::where('wallet_id', $wallet->id)->latest()->limit(20)->get()
            : collect();

        $banks = [];

        try {
            $banks = DuitkuDisbursementService::make()->listBanks();
        } catch (Throwable $e) {
            // Lihat komentar sama di WalletWithdrawalController::index().
        }

        return view('keuangan.withdrawal.index', compact('branches', 'branch', 'wallet', 'riwayat', 'banks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'branch_office_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:10000'],
            'bank_code' => ['required', 'string', 'max:10'],
            'bank_account' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ]);

        $context = $this->companyContext($request);
        $company = $context->company;

        $branchOfficeId = $context->isLockedToBranch()
            ? $context->branchOffice?->id
            : $validated['branch_office_id'];

        $branch = BranchOffice::where('company_id', $company->id)
            ->where('id', $branchOfficeId)
            ->firstOrFail();

        $wallet = WalletProvisioningService::forBranch($branch->id);

        try {
            $this->service->request(
                $wallet,
                $request->user(),
                (float) $validated['amount'],
                $validated['bank_code'],
                $validated['bank_account'],
                $validated['account_name'],
                $validated['purpose'] ?? null,
                $company->id,
                $branch->id,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('keuangan.withdrawal.index', ['branch_office_id' => $branch->id])
            ->with('success', 'Permintaan tarik saldo diajukan, menunggu persetujuan.');
    }
}
