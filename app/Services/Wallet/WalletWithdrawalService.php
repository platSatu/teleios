<?php

namespace App\Services\Wallet;

use App\Models\AuditLog;
use App\Models\DuitkuDisbursementSetting;
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
 * SALDO DITAHAN SAAT PENGAJUAN (30 September 2026): request() langsung
 * memotong saldo (ditahan) dalam 1 transaksi, jadi saldo yang sama tidak
 * bisa dipakai transfer/beli paket selagi menunggu approval. Dikembalikan
 * otomatis kalau ditolak, dibatalkan, atau Duitku MENOLAK sebelum uang
 * dikirim (inquiry gagal). Kalau hasil transfer tidak pasti (respons bukan
 * sukses, timeout, koneksi putus), statusnya 'needs_review' dan saldo
 * TIDAK dikembalikan otomatis -- admin cek ke Duitku lalu menandai hasilnya
 * (resolveReview()). Uang tidak pernah bisa keluar tanpa saldo terpotong.
 *
 * Permintaan lama (sebelum fitur ini, held_at null) ditahan saat disetujui.
 *
 * BIAYA PENARIKAN (2 Oktober 2026): biaya per penarikan diisi superadmin
 * (DuitkuDisbursementSetting::withdrawal_fee) dan di-snapshot ke
 * permintaan saat diajukan. Saldo dipotong sebesar amount, yang dikirim ke
 * rekening = amount - fee (net_amount). Di Ledger tercatat 2 baris:
 * WITHDRAWAL (net) dan WITHDRAWAL_FEE (biaya), dikembalikan berpasangan.
 * Semua angka dihitung dalam Rupiah utuh (integer) supaya tidak ada selisih
 * pembulatan.
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
        ?int $expectedFee = null,
    ): WalletWithdrawal {
        // Rupiah utuh saja: Duitku mentransfer dalam integer, jadi pecahan
        // ditolak di depan daripada dibulatkan diam-diam.
        if ($amount <= 0 || floor($amount) !== $amount) {
            throw new RuntimeException('Jumlah tarik saldo harus berupa angka Rupiah bulat lebih dari 0.');
        }

        $amount = (int) $amount;

        return DB::transaction(function () use ($wallet, $requestedBy, $amount, $bankCode, $bankAccount, $accountName, $purpose, $companyId, $branchOfficeId, $bankAccountId, $expectedFee) {
            $lockedWallet = Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            // Biaya selalu dari database, bukan dari form. $expectedFee hanya
            // dipakai untuk memastikan biaya yang dilihat user di form masih
            // sama (mis. superadmin baru saja mengubahnya) -- kalau beda, batal.
            $fee = DuitkuDisbursementSetting::current()->withdrawalFee();

            if ($expectedFee !== null && $expectedFee !== $fee) {
                throw new RuntimeException('Biaya penarikan baru saja berubah menjadi Rp '.number_format($fee, 0, ',', '.').'. Silakan cek kembali jumlah yang akan diterima, lalu ajukan ulang.');
            }

            $net = $amount - $fee;

            if ($net < self::MIN_TRANSFER) {
                throw new RuntimeException('Jumlah yang diterima setelah biaya penarikan (Rp '.number_format($fee, 0, ',', '.').') minimal Rp '.number_format(self::MIN_TRANSFER, 0, ',', '.').'. Silakan tarik minimal Rp '.number_format($fee + self::MIN_TRANSFER, 0, ',', '.').'.');
            }

            // Permintaan lama yang belum ditahan tetap mengurangi saldo tersedia.
            $legacyInFlight = (float) WalletWithdrawal::where('wallet_id', $wallet->id)
                ->whereNull('held_at')
                ->whereIn('status', [WalletWithdrawal::STATUS_PENDING_APPROVAL, WalletWithdrawal::STATUS_APPROVED, WalletWithdrawal::STATUS_PROCESSING])
                ->sum('amount');
            $available = (float) $lockedWallet->balance - $legacyInFlight;

            if ($amount > $available) {
                throw new RuntimeException('Saldo tidak mencukupi. Saldo tersedia: Rp '.number_format(max(0, $available), 0, ',', '.'));
            }

            $withdrawal = WalletWithdrawal::create([
                'wallet_id' => $wallet->id,
                'requested_by' => $requestedBy->id,
                'company_id' => $companyId,
                'branch_office_id' => $branchOfficeId,
                'bank_account_id' => $bankAccountId,
                'amount' => $amount,
                'fee_amount' => $fee,
                'net_amount' => $net,
                'bank_code' => $bankCode,
                'bank_account' => $bankAccount,
                'account_name' => $accountName,
                'purpose' => $purpose,
                'status' => WalletWithdrawal::STATUS_PENDING_APPROVAL,
            ]);

            $this->hold($withdrawal, $lockedWallet, $requestedBy->id);

            return $withdrawal->fresh();
        });
    }

    /** Kode respons transfer Duitku yang pasti sukses. Selain ini hasilnya dianggap belum pasti. */
    private const DUITKU_SUCCESS = '00';

    /** Jumlah minimal yang dikirim ke rekening (setelah biaya). */
    public const MIN_TRANSFER = 10000;

    public function approveAndProcess(WalletWithdrawal $withdrawal, User $approver): WalletWithdrawal
    {
        DB::transaction(function () use ($withdrawal, $approver) {
            $this->claimPending($withdrawal, [
                'status' => WalletWithdrawal::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            // Permintaan lama yang belum ditahan: tahan sekarang, SEBELUM uang dikirim.
            // Kalau saldo sudah tidak cukup, persetujuan ikut dibatalkan.
            $fresh = $withdrawal->fresh();

            if (! $fresh->held_at) {
                try {
                    $this->hold($fresh, Wallet::whereKey($fresh->wallet_id)->firstOrFail(), $approver->id);
                } catch (RuntimeException) {
                    throw new RuntimeException('Saldo user sudah tidak cukup untuk permintaan ini. Silakan tolak permintaannya.');
                }
            }
        });

        return $this->process($withdrawal->fresh());
    }

    public function reject(WalletWithdrawal $withdrawal, User $approver, string $reason): WalletWithdrawal
    {
        DB::transaction(function () use ($withdrawal, $approver, $reason) {
            $this->claimPending($withdrawal, [
                'status' => WalletWithdrawal::STATUS_REJECTED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->refund($withdrawal, 'ditolak', $approver->id);
        });

        return $withdrawal->fresh();
    }

    public function cancel(WalletWithdrawal $withdrawal, User $canceller): WalletWithdrawal
    {
        if ($withdrawal->requested_by !== $canceller->id) {
            throw new RuntimeException('Hanya pemohon sendiri yang bisa membatalkan permintaan ini.');
        }

        DB::transaction(function () use ($withdrawal, $canceller) {
            $this->claimPending($withdrawal, ['status' => WalletWithdrawal::STATUS_CANCELLED], 'Hanya permintaan yang masih menunggu persetujuan yang bisa dibatalkan.');
            $this->refund($withdrawal, 'dibatalkan', $canceller->id);
        });

        return $withdrawal->fresh();
    }

    /**
     * Status transfer yang belum pasti, ditanyakan ke Duitku (hanya baca).
     * Hasilnya ditampilkan ke admin untuk dicocokkan sebelum resolveReview().
     */
    public function checkReview(WalletWithdrawal $withdrawal): string
    {
        if (! $withdrawal->needsReview()) {
            throw new RuntimeException('Permintaan ini tidak sedang menunggu pengecekan.');
        }

        if (! $withdrawal->duitku_disburse_id) {
            return 'Belum ada ID transaksi Duitku (transfer belum sempat dikirim). Cek juga di dashboard Duitku › Laporan Disbursement.';
        }

        try {
            $status = DuitkuDisbursementService::make()->inquiryStatus($withdrawal->duitku_disburse_id);
        } catch (Throwable $e) {
            return 'Duitku belum bisa dihubungi: '.$e->getMessage();
        }

        return 'Jawaban Duitku: '.($status['responseDesc'] ?? '-').' (kode '.($status['responseCode'] ?? '?').'). Cocokkan juga dengan dashboard Duitku › Laporan Disbursement.';
    }

    /**
     * Admin menandai hasil transfer yang belum pasti setelah mengecek ke
     * Duitku: $sent = true -> sukses (saldo tetap terpotong); false -> gagal,
     * saldo dikembalikan.
     */
    public function resolveReview(WalletWithdrawal $withdrawal, bool $sent, User $admin, string $note): WalletWithdrawal
    {
        DB::transaction(function () use ($withdrawal, $sent, $admin, $note) {
            $locked = WalletWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! $locked->needsReview()) {
                throw new RuntimeException('Permintaan ini sudah diselesaikan sebelumnya.');
            }

            $locked->update([
                'status' => $sent ? WalletWithdrawal::STATUS_SUCCESS : WalletWithdrawal::STATUS_FAILED,
                'failure_reason' => trim(($locked->failure_reason ? $locked->failure_reason.' | ' : '').'Dicek '.$admin->name.': '.$note),
                'processed_at' => now(),
            ]);

            if (! $sent) {
                $this->refund($locked, 'transfer tidak terkirim', $admin->id);
            }

            AuditLog::record('wallet_withdrawal.resolve_review', WalletWithdrawal::class, $locked->id, null, ['sent' => $sent, 'note' => $note], $admin);
        });

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
     * Eksekusi: inquiry lalu transfer ke Duitku. Saldo SUDAH ditahan, jadi
     * sukses cukup ubah status. Ditolak sebelum uang dikirim -> gagal +
     * saldo dikembalikan. Hasil transfer tidak pasti -> needs_review (tidak
     * dikembalikan otomatis, lihat resolveReview()).
     */
    private function process(WalletWithdrawal $withdrawal): WalletWithdrawal
    {
        $withdrawal->update(['status' => WalletWithdrawal::STATUS_PROCESSING]);
        $transferAttempted = false;
        $inquiry = null;

        try {
            $duitku = DuitkuDisbursementService::make();
            // Yang dikirim = saldo dipotong - biaya (lihat docblock class).
            $amount = (int) round($withdrawal->transferAmount());
            $purpose = $withdrawal->purpose ?: 'Tarik Saldo '.config('app.name');

            // Rekening terdaftar: nomor lengkap diambil dari bank_accounts (terenkripsi),
            // wallet_withdrawals hanya menyimpan versi tersamar.
            $bankAccount = $withdrawal->bankAccount?->account_number ?? $withdrawal->bank_account;

            $inquiry = $duitku->inquiry($withdrawal->bank_code, $bankAccount, $amount, $purpose);

            if (($inquiry['responseCode'] ?? null) !== self::DUITKU_SUCCESS || ! $inquiry['disburseId']) {
                return $this->fail($withdrawal, 'Inquiry Duitku ditolak: '.($inquiry['responseDesc'] ?? 'unknown').' (kode '.($inquiry['responseCode'] ?? '?').')', ['inquiry' => $inquiry['raw'] ?? null]);
            }

            // Rekening terdaftar: nama di bank saat ini harus tetap sama
            // dengan nama saat rekening didaftarkan.
            if ($withdrawal->bank_account_id && ! BankAccountService::sameName($inquiry['accountName'], $withdrawal->account_name)) {
                return $this->fail($withdrawal, 'Nama pemilik rekening di bank ('.($inquiry['accountName'] ?: '-').') tidak sama dengan rekening terdaftar ('.$withdrawal->account_name.'). Dana tidak dikirim.');
            }

            // Simpan ID Duitku SEBELUM transfer, supaya hasil yang tidak pasti bisa dicek ulang.
            $custRef = $duitku->generateCustRefNumber();
            $withdrawal->update(['duitku_disburse_id' => $inquiry['disburseId'], 'duitku_cust_ref_number' => $custRef]);

            $transferAttempted = true;
            $transfer = $duitku->transfer(
                $inquiry['disburseId'],
                $withdrawal->bank_code,
                $bankAccount,
                $amount,
                $inquiry['accountName'] ?: $withdrawal->account_name,
                $purpose,
                $custRef,
            );

            $responses = ['inquiry' => $inquiry['raw'], 'transfer' => $transfer['raw']];

            if (($transfer['responseCode'] ?? null) !== self::DUITKU_SUCCESS) {
                return $this->markNeedsReview($withdrawal, 'Respons transfer Duitku: '.($transfer['responseDesc'] ?? 'unknown').' (kode '.($transfer['responseCode'] ?? '?').')', $responses);
            }

            $withdrawal->update([
                'status' => WalletWithdrawal::STATUS_SUCCESS,
                'duitku_response' => $responses,
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::error('wallet-withdrawal: gagal diproses', ['withdrawal_id' => $withdrawal->id, 'error' => $e->getMessage()]);

            return $transferAttempted
                ? $this->markNeedsReview($withdrawal, 'Koneksi ke Duitku bermasalah saat transfer: '.$e->getMessage(), ['inquiry' => $inquiry['raw'] ?? null])
                : $this->fail($withdrawal, $e->getMessage());
        }

        return $withdrawal->fresh();
    }

    /** Uang pasti belum dikirim: gagal + saldo dikembalikan. */
    private function fail(WalletWithdrawal $withdrawal, string $reason, ?array $responses = null): WalletWithdrawal
    {
        DB::transaction(function () use ($withdrawal, $reason, $responses) {
            $withdrawal->update(array_filter([
                'status' => WalletWithdrawal::STATUS_FAILED,
                'failure_reason' => $reason,
                'duitku_response' => $responses,
                'processed_at' => now(),
            ], fn ($value) => $value !== null));

            $this->refund($withdrawal, 'gagal diproses', $withdrawal->approved_by);
        });

        return $withdrawal->fresh();
    }

    /** Hasil transfer belum pasti: saldo TETAP ditahan sampai admin mengecek. */
    private function markNeedsReview(WalletWithdrawal $withdrawal, string $reason, array $responses): WalletWithdrawal
    {
        $withdrawal->update([
            'status' => WalletWithdrawal::STATUS_NEEDS_REVIEW,
            'failure_reason' => $reason,
            'duitku_response' => $responses,
            'processed_at' => now(),
        ]);

        Log::critical('wallet-withdrawal: hasil transfer belum pasti, perlu dicek admin', ['withdrawal_id' => $withdrawal->id, 'reason' => $reason]);

        return $withdrawal->fresh();
    }

    /**
     * Tahan (potong) saldo untuk permintaan ini: jumlah yang dikirim + biaya,
     * 2 baris Ledger dalam transaksi pemanggil. Melempar RuntimeException kalau
     * saldo kurang (seluruh transaksi batal, tidak ada potongan setengah).
     */
    private function hold(WalletWithdrawal $withdrawal, Wallet $wallet, ?string $actorId): void
    {
        $target = $withdrawal->bank_code.' '.$withdrawal->bank_account;

        WalletLedgerService::debit(
            $wallet,
            $withdrawal->transferAmount(),
            WalletWithdrawal::class,
            $withdrawal->id,
            'Ditahan untuk tarik saldo ke '.$target,
            $actorId,
            'WITHDRAWAL',
        );

        if ((float) $withdrawal->fee_amount > 0) {
            WalletLedgerService::debit(
                $wallet,
                (float) $withdrawal->fee_amount,
                WalletWithdrawal::class,
                $withdrawal->id,
                'Biaya tarik saldo ke '.$target,
                $actorId,
                'WITHDRAWAL_FEE',
            );
        }

        $withdrawal->update(['held_at' => now()]);
    }

    /** Kembalikan saldo yang ditahan -- sekali saja per permintaan. */
    private function refund(WalletWithdrawal $withdrawal, string $reason, ?string $actorId): void
    {
        $locked = WalletWithdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

        if (! $locked->held_at || $locked->refunded_at) {
            return;
        }

        $wallet = Wallet::whereKey($locked->wallet_id)->firstOrFail();

        WalletLedgerService::credit(
            $wallet,
            $locked->transferAmount(),
            WalletWithdrawal::class,
            $locked->id,
            "Pengembalian tarik saldo ({$reason})",
            $actorId,
            'WITHDRAWAL_REFUND',
        );

        if ((float) $locked->fee_amount > 0) {
            WalletLedgerService::credit(
                $wallet,
                (float) $locked->fee_amount,
                WalletWithdrawal::class,
                $locked->id,
                "Pengembalian biaya tarik saldo ({$reason})",
                $actorId,
                'WITHDRAWAL_FEE_REFUND',
            );
        }

        $locked->update(['refunded_at' => now()]);
    }
}
