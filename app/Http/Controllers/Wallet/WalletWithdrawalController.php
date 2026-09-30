<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Concerns\VerifiesTransactionPin;
use App\Http\Controllers\Controller;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\BankAccountService;
use App\Services\Wallet\WalletWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * "Tarik Saldo" untuk Wallet PRIBADI logged-in user (pengajar & reseller
 * memakai controller yang sama persis — keduanya cuma User biasa dengan
 * satu Wallet, lihat diskusi 22 September 2026). Diakses dari dropdown
 * profil header, di samping "Top Up"/"Transfer Saldo" yang sudah ada.
 *
 * Untuk Wallet milik BranchOffice (ditarik Company), lihat
 * App\Http\Controllers\Keuangan\BranchWithdrawalController — service
 * & model yang mendasarinya sama, cuma beda wallet mana yang dituju
 * dan siapa yang boleh mengajukan.
 */
class WalletWithdrawalController extends Controller
{
    use VerifiesTransactionPin;

    public function __construct(
        protected WalletWithdrawalService $service,
        protected BankAccountService $bankAccounts,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $wallet = $user->wallet;

        $riwayat = $wallet
            ? WalletWithdrawal::with('bankAccount')->where('wallet_id', $wallet->id)->latest()->limit(20)->get()
            : collect();

        // Dana selalu dikirim ke rekening terdaftar (lihat BankAccountService),
        // tidak lagi diketik ulang setiap kali tarik saldo.
        return view('wallet.withdrawal.index', [
            'wallet' => $wallet,
            'riwayat' => $riwayat,
            'bankAccount' => $this->bankAccounts->current($user),
            'nextBankAccount' => $this->bankAccounts->latestSubmission($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10000'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ]);

        if ($failed = $this->failedTransactionPin($request)) {
            return $failed;
        }

        $user = $request->user();
        $wallet = $user->wallet;

        if (! $wallet) {
            return back()->with('error', 'Wallet Anda tidak ditemukan.');
        }

        $account = $this->bankAccounts->current($user);

        if (! $account) {
            return redirect()->route('wallet.bank-account.index')
                ->with('error', 'Anda belum punya rekening pencairan yang aktif. Tambahkan dulu supaya bisa tarik saldo.');
        }

        // Resolusi company/branch buat routing approval (lihat
        // WalletWithdrawalService::canApprove()) -- diambil dari
        // keanggotaan company user ini, KALAU ada. User tanpa
        // keanggotaan company manapun (reseller lepas, murni penerima
        // komisi referral) tetap boleh mengajukan, cuma nanti hanya
        // superadmin yang bisa approve punya dia (lihat canApprove()).
        $membership = $user->companyMemberships()->whereNotNull('company_id')->first();

        try {
            $this->service->request(
                $wallet,
                $user,
                (float) $validated['amount'],
                $account->bank_code,
                '****'.$account->account_last4, // nomor lengkap tetap hanya di bank_accounts (terenkripsi)
                $account->account_name,
                $validated['purpose'] ?? null,
                $membership?->company_id,
                $membership?->branch_office_id,
                $account->id,
            );
        } catch (RuntimeException $e) {
            return back()->withInput($request->except('pin'))->with('error', $e->getMessage());
        }

        return redirect()
            ->route('wallet.withdrawal.index')
            ->with('success', 'Permintaan tarik saldo berhasil diajukan, menunggu persetujuan admin.');
    }

    public function cancel(Request $request, string $id): RedirectResponse
    {
        $withdrawal = WalletWithdrawal::where('wallet_id', $request->user()->wallet?->id)
            ->where('id', $id)
            ->firstOrFail();

        try {
            $this->service->cancel($withdrawal, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Permintaan tarik saldo dibatalkan.');
    }
}
