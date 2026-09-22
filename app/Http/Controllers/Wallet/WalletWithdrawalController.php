<?php

namespace App\Http\Controllers\Wallet;

use App\Http\Controllers\Controller;
use App\Models\WalletWithdrawal;
use App\Services\Payment\DuitkuDisbursementService;
use App\Services\Wallet\WalletWithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

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
    public function __construct(protected WalletWithdrawalService $service)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $wallet = $user->wallet;

        $riwayat = $wallet
            ? WalletWithdrawal::where('wallet_id', $wallet->id)->latest()->limit(20)->get()
            : collect();

        // Daftar bank buat dropdown "Bank Tujuan" -- gagal-aman kalau
        // kredensial Duitku Disbursement belum diisi superadmin (lihat
        // DuitkuDisbursementService::make()), form tetap tampil, cuma
        // dropdown-nya kosong/fallback ke input manual di view.
        $banks = [];

        try {
            $banks = DuitkuDisbursementService::make()->listBanks();
        } catch (Throwable $e) {
            // Sengaja diam -- lihat komentar di atas.
        }

        return view('wallet.withdrawal.index', compact('wallet', 'riwayat', 'banks'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10000'],
            'bank_code' => ['required', 'string', 'max:10'],
            'bank_account' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $wallet = $user->wallet;

        if (! $wallet) {
            return back()->with('error', 'Wallet Anda tidak ditemukan.');
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
                $validated['bank_code'],
                $validated['bank_account'],
                $validated['account_name'],
                $validated['purpose'] ?? null,
                $membership?->company_id,
                $membership?->branch_office_id,
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
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
