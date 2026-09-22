<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletWithdrawal;
use App\Services\Company\CompanyContext;
use App\Services\Payment\DuitkuDisbursementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Fitur "Tarik Saldo" (diskusi 22 September 2026) -- satu service
 * dipakai untuk SEMUA jenis penarik (pengajar/reseller menarik Wallet
 * sendiri, Company menarik Wallet Branch), lihat docblock
 * App\Models\WalletWithdrawal.
 *
 * request() TIDAK langsung memindahkan uang -- cuma masuk antrean
 * pending_approval. approveAndProcess() yang benar-benar memicu
 * panggilan Duitku Disbursement (inquiry lalu transfer) DAN mendebit
 * Wallet, TAPI HANYA setelah Duitku dengan tegas bilang sukses --
 * Wallet TIDAK PERNAH didebit duluan lalu di-refund kalau transfer
 * gagal, supaya tidak ada jendela waktu di mana saldo internal dan
 * kenyataan Duitku bisa berbeda.
 */
class WalletWithdrawalService
{
    public function request(
        Wallet $wallet,
        User $requestedBy,
        float $amount,
        string $bankCode,
        string $bankAccount,
        string $accountName,
        ?string $purpose,
        ?string $companyId,
        ?string $branchOfficeId,
    ): WalletWithdrawal {
        if ($amount <= 0) {
            throw new RuntimeException('Jumlah tarik saldo harus lebih besar dari 0.');
        }

        // Cek kasar (bukan reservasi/hold) -- pengecekan yang benar-benar
        // atomic & tidak bisa ditembus tetap ada di WalletLedgerService::
        // debit() nanti saat approveAndProcess(). Ini cuma mencegah
        // permintaan yang jelas-jelas tidak masuk akal masuk antrean
        // approval duluan.
        if ($amount > (float) $wallet->balance) {
            throw new RuntimeException('Saldo tidak mencukupi. Saldo tersedia: Rp '.number_format((float) $wallet->balance, 0, ',', '.'));
        }

        return WalletWithdrawal::create([
            'wallet_id' => $wallet->id,
            'requested_by' => $requestedBy->id,
            'company_id' => $companyId,
            'branch_office_id' => $branchOfficeId,
            'amount' => $amount,
            'bank_code' => $bankCode,
            'bank_account' => $bankAccount,
            'account_name' => $accountName,
            'purpose' => $purpose,
            'status' => WalletWithdrawal::STATUS_PENDING_APPROVAL,
        ]);
    }

    public function approveAndProcess(WalletWithdrawal $withdrawal, User $approver): WalletWithdrawal
    {
        if (! $withdrawal->isPending()) {
            throw new RuntimeException('Permintaan ini sudah diproses sebelumnya.');
        }

        $withdrawal->update([
            'status' => WalletWithdrawal::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $this->process($withdrawal->fresh());
    }

    public function reject(WalletWithdrawal $withdrawal, User $approver, string $reason): WalletWithdrawal
    {
        if (! $withdrawal->isPending()) {
            throw new RuntimeException('Permintaan ini sudah diproses sebelumnya.');
        }

        $withdrawal->update([
            'status' => WalletWithdrawal::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $withdrawal;
    }

    public function cancel(WalletWithdrawal $withdrawal, User $canceller): WalletWithdrawal
    {
        if (! $withdrawal->isPending()) {
            throw new RuntimeException('Hanya permintaan yang masih menunggu persetujuan yang bisa dibatalkan.');
        }

        if ($withdrawal->requested_by !== $canceller->id) {
            throw new RuntimeException('Hanya pemohon sendiri yang bisa membatalkan permintaan ini.');
        }

        $withdrawal->update(['status' => WalletWithdrawal::STATUS_CANCELLED]);

        return $withdrawal;
    }

    /**
     * Siapa yang berwenang approve/reject permintaan ini -- keputusan
     * user 22 September 2026: "approve admin untuk branch yang
     * melakukan pembayaran atau user yang pertama kali daftar".
     * Diinterpretasikan lewat App\Services\Company\CompanyContext,
     * pola yang sama dipakai seluruh controller lain (Tagihan, Transfer
     * Fee): staff yang TERKUNCI ke satu branch boleh approve kalau
     * branch itu sama dengan branch_office_id permintaan; staff yang
     * TIDAK terkunci (owner/company-wide) boleh approve kalau
     * company_id-nya sama. Permintaan tanpa company/branch (reseller
     * lepas tanpa afiliasi company manapun) HANYA superadmin.
     */
    public function canApprove(WalletWithdrawal $withdrawal, User $user, ?CompanyContext $context): bool
    {
        if ($user->user_type === 'SUPERADMIN') {
            return true;
        }

        if (! $withdrawal->company_id) {
            return false;
        }

        if (! $context || $context->company->id !== $withdrawal->company_id) {
            return false;
        }

        if ($context->isLockedToBranch()) {
            return $context->branchOffice?->id === $withdrawal->branch_office_id;
        }

        return true;
    }

    /**
     * Eksekusi beneran: inquiry lalu transfer ke Duitku, baru debit
     * Wallet kalau transfer dengan tegas sukses. Kegagalan di titik
     * MANAPUN (inquiry ditolak, transfer ditolak, exception jaringan)
     * ditangkap di sini dan berhenti di status 'failed' -- Wallet tidak
     * tersentuh sama sekali, jadi tidak perlu logika refund.
     */
    private function process(WalletWithdrawal $withdrawal): WalletWithdrawal
    {
        $withdrawal->update(['status' => WalletWithdrawal::STATUS_PROCESSING]);

        try {
            $duitku = DuitkuDisbursementService::make();
            $amount = (int) round((float) $withdrawal->amount);
            $purpose = $withdrawal->purpose ?: 'Tarik Saldo Konexa';

            $inquiry = $duitku->inquiry($withdrawal->bank_code, $withdrawal->bank_account, $amount, $purpose);

            if (($inquiry['responseCode'] ?? null) !== '00' || ! $inquiry['disburseId']) {
                throw new RuntimeException(
                    'Inquiry Duitku ditolak: '.($inquiry['responseDesc'] ?? 'unknown').' (kode '.($inquiry['responseCode'] ?? '?').')'
                );
            }

            $transfer = $duitku->transfer(
                $inquiry['disburseId'],
                $withdrawal->bank_code,
                $withdrawal->bank_account,
                $amount,
                $inquiry['accountName'] ?: $withdrawal->account_name,
                $purpose,
            );

            $responseCode = $transfer['responseCode'] ?? null;

            if ($responseCode !== '00') {
                throw new RuntimeException(
                    'Transfer Duitku ditolak: '.($transfer['responseDesc'] ?? 'unknown').' (kode '.($responseCode ?? '?').')'
                );
            }

            DB::transaction(function () use ($withdrawal, $inquiry, $transfer) {
                $lockedWallet = Wallet::whereKey($withdrawal->wallet_id)->lockForUpdate()->firstOrFail();

                WalletLedgerService::debit(
                    $lockedWallet,
                    (float) $withdrawal->amount,
                    WalletWithdrawal::class,
                    $withdrawal->id,
                    'Tarik saldo ke '.$withdrawal->bank_code.' '.$withdrawal->bank_account,
                    $withdrawal->approved_by,
                    'WITHDRAWAL',
                );

                $withdrawal->update([
                    'status' => WalletWithdrawal::STATUS_SUCCESS,
                    'duitku_disburse_id' => $inquiry['disburseId'],
                    'duitku_cust_ref_number' => $transfer['custRefNumber'],
                    'duitku_response' => ['inquiry' => $inquiry['raw'], 'transfer' => $transfer['raw']],
                    'processed_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $withdrawal->update([
                'status' => WalletWithdrawal::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('wallet-withdrawal: gagal diproses', [
                'withdrawal_id' => $withdrawal->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $withdrawal->fresh();
    }
}
