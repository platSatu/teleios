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
        ?string $bankAccountId = null,
    ): WalletWithdrawal {
        if ($amount <= 0) {
            throw new RuntimeException('Jumlah tarik saldo harus lebih besar dari 0.');
        }

        // Cek kasar (bukan reservasi/hold) -- pengecekan yang benar-benar
        // atomic & tidak bisa ditembus tetap ada di WalletLedgerService::
        // debit() nanti saat approveAndProcess(). Saldo tersedia sudah
        // dikurangi penarikan lain yang masih antre/diproses, supaya
        // beberapa permintaan tidak bisa bersama-sama melebihi saldo.
        $inFlight = (float) WalletWithdrawal::where('wallet_id', $wallet->id)
            ->whereIn('status', [WalletWithdrawal::STATUS_PENDING_APPROVAL, WalletWithdrawal::STATUS_APPROVED, WalletWithdrawal::STATUS_PROCESSING])
            ->sum('amount');
        $available = (float) $wallet->balance - $inFlight;

        if ($amount > $available) {
            throw new RuntimeException('Saldo tidak mencukupi. Saldo tersedia: Rp '.number_format(max(0, $available), 0, ',', '.').($inFlight > 0 ? ' (sudah dikurangi penarikan yang sedang diproses).' : '.'));
        }

        return WalletWithdrawal::create([
            'wallet_id' => $wallet->id,
            'requested_by' => $requestedBy->id,
            'company_id' => $companyId,
            'branch_office_id' => $branchOfficeId,
            'bank_account_id' => $bankAccountId,
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
        $this->claimPending($withdrawal, [
            'status' => WalletWithdrawal::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $this->process($withdrawal->fresh());
    }

    public function reject(WalletWithdrawal $withdrawal, User $approver, string $reason): WalletWithdrawal
    {
        $this->claimPending($withdrawal, [
            'status' => WalletWithdrawal::STATUS_REJECTED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $withdrawal->fresh();
    }

    public function cancel(WalletWithdrawal $withdrawal, User $canceller): WalletWithdrawal
    {
        if ($withdrawal->requested_by !== $canceller->id) {
            throw new RuntimeException('Hanya pemohon sendiri yang bisa membatalkan permintaan ini.');
        }

        $this->claimPending($withdrawal, ['status' => WalletWithdrawal::STATUS_CANCELLED], 'Hanya permintaan yang masih menunggu persetujuan yang bisa dibatalkan.');

        return $withdrawal->fresh();
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
     * Ubah status HANYA kalau masih pending_approval, dalam satu query
     * atomic -- kalau dua aksi (setujui/tolak/batal) datang bersamaan,
     * hanya satu yang menang, jadi dana tidak pernah terkirim dua kali
     * dan permintaan yang sedang dibayar tidak bisa berubah jadi ditolak.
     */
    private function claimPending(WalletWithdrawal $withdrawal, array $changes, string $error = 'Permintaan ini sudah diproses sebelumnya.'): void
    {
        $claimed = WalletWithdrawal::whereKey($withdrawal->id)
            ->where('status', WalletWithdrawal::STATUS_PENDING_APPROVAL)
            ->update($changes + ['updated_at' => now()]);

        if (! $claimed) {
            throw new RuntimeException($error);
        }
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
            $purpose = $withdrawal->purpose ?: 'Tarik Saldo '.config('app.name');

            // Rekening terdaftar: nomor lengkap diambil dari bank_accounts (terenkripsi),
            // wallet_withdrawals hanya menyimpan versi tersamar.
            $bankAccount = $withdrawal->bankAccount?->account_number ?? $withdrawal->bank_account;

            $inquiry = $duitku->inquiry($withdrawal->bank_code, $bankAccount, $amount, $purpose);

            if (($inquiry['responseCode'] ?? null) !== '00' || ! $inquiry['disburseId']) {
                throw new RuntimeException(
                    'Inquiry Duitku ditolak: '.($inquiry['responseDesc'] ?? 'unknown').' (kode '.($inquiry['responseCode'] ?? '?').')'
                );
            }

            // Rekening terdaftar: nama di bank saat ini harus tetap sama
            // dengan nama saat rekening didaftarkan.
            if ($withdrawal->bank_account_id && ! BankAccountService::sameName($inquiry['accountName'], $withdrawal->account_name)) {
                throw new RuntimeException(
                    'Nama pemilik rekening di bank ('.($inquiry['accountName'] ?: '-').') tidak sama dengan rekening terdaftar ('.$withdrawal->account_name.'). Dana tidak dikirim.'
                );
            }

            $transfer = $duitku->transfer(
                $inquiry['disburseId'],
                $withdrawal->bank_code,
                $bankAccount,
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
