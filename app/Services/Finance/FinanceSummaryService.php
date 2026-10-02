<?php

namespace App\Services\Finance;

use App\Models\Deposit;
use App\Models\LedgerEntry;
use App\Models\Subscription;
use App\Models\Wallet;
use App\Models\WalletWithdrawal;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Ringkasan keuangan untuk halaman Data Deposit superadmin (2 Oktober 2026):
 * deposit, disbursement (tarik saldo), penjualan paket, dan sisa saldo user.
 * Semua angka mengikuti rentang tanggal ($from/$to, kolom created_at) dan
 * user yang dipilih. Hanya membaca (SELECT agregat), tidak mengubah data,
 * dan setiap kelompok cukup 1 query GROUP BY.
 */
class FinanceSummaryService
{
    /** Status tarik saldo yang masih berjalan / sudah berakhir tanpa uang terkirim. */
    public const WITHDRAWAL_PENDING = [
        WalletWithdrawal::STATUS_PENDING_APPROVAL,
        WalletWithdrawal::STATUS_APPROVED,
        WalletWithdrawal::STATUS_PROCESSING,
        WalletWithdrawal::STATUS_NEEDS_REVIEW,
    ];

    public const WITHDRAWAL_FAILED = [
        WalletWithdrawal::STATUS_FAILED,
        WalletWithdrawal::STATUS_REJECTED,
        WalletWithdrawal::STATUS_CANCELLED,
    ];

    public function __construct(
        private readonly ?Carbon $from = null,
        private readonly ?Carbon $to = null,
        private readonly ?string $userId = null,
    ) {
    }

    /** Batasi query ke rentang tanggal (>= / <= supaya index created_at terpakai). */
    public function inRange(Builder $query, string $column = 'created_at'): Builder
    {
        return $query
            ->when($this->from, fn ($q) => $q->where($column, '>=', $this->from))
            ->when($this->to, fn ($q) => $q->where($column, '<=', $this->to));
    }

    /** Subquery id wallet pribadi milik user yang difilter (atau semua user). */
    public function userWalletIds(): Builder
    {
        return Wallet::query()
            ->select('id')
            ->whereNotNull('user_id')
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId));
    }

    public function deposits(): array
    {
        $rows = $this->inRange(Deposit::query())
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->selectRaw('status, COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (string $status) => (int) ($rows[$status]->total_count ?? 0);

        return [
            'statuses' => $rows->keys()->all(),
            'total' => (int) $rows->sum('total_count'),
            'success' => $count('SUCCESS'),
            'success_amount' => (float) ($rows['SUCCESS']->total_amount ?? 0),
            'pending' => $count('PENDING'),
            'failed' => $count('FAILED'),
            'expired' => $count('EXPIRED'),
        ];
    }

    public function disbursements(): array
    {
        $rows = $this->inRange(WalletWithdrawal::query())
            ->when($this->userId, fn ($q) => $q->whereIn('wallet_id', $this->userWalletIds()))
            ->selectRaw('status, COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount,
                COALESCE(SUM(COALESCE(net_amount, amount)), 0) AS total_net, COALESCE(SUM(fee_amount), 0) AS total_fee')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $success = $rows[WalletWithdrawal::STATUS_SUCCESS] ?? null;
        $countOf = fn (array $statuses) => (int) $rows->only($statuses)->sum('total_count');

        return [
            'total' => (int) $rows->sum('total_count'),
            'total_amount' => (float) $rows->sum('total_amount'),
            'success' => (int) ($success->total_count ?? 0),
            'success_net' => (float) ($success->total_net ?? 0),
            // Biaya hanya dihitung dari penarikan sukses; yang gagal/ditolak dikembalikan ke user.
            'fee' => (float) ($success->total_fee ?? 0),
            'pending' => $countOf(self::WITHDRAWAL_PENDING),
            'failed' => $countOf(self::WITHDRAWAL_FAILED),
        ];
    }

    public function packageSales(): array
    {
        $row = $this->inRange(Subscription::query())
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->selectRaw('COUNT(*) AS total_count, COALESCE(SUM(amount), 0) AS total_amount')
            ->first();

        return ['count' => (int) $row->total_count, 'amount' => (float) $row->total_amount];
    }

    /**
     * Sisa saldo user (wallet pribadi, bukan wallet branch) per akhir periode.
     * Saldo sekarang dikurangi semua mutasi Ledger SETELAH tanggal akhir --
     * tidak bergantung urutan baris Ledger, jadi tetap tepat walau beberapa
     * mutasi tercatat di detik yang sama.
     */
    public function userBalance(): float
    {
        $current = (float) Wallet::query()
            ->whereNotNull('user_id')
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->sum('balance');

        if (! $this->to || $this->to->isFuture()) {
            return $current;
        }

        $movedAfter = (float) LedgerEntry::query()
            ->whereIn('wallet_id', $this->userWalletIds())
            ->where('created_at', '>', $this->to)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'CREDIT' THEN amount ELSE -amount END), 0) AS net")
            ->first()
            ->net;

        return $current - $movedAfter;
    }
}
