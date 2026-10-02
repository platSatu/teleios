<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu permintaan "Tarik Saldo" ke rekening bank lewat Duitku
 * Disbursement -- lihat docblock migration create_wallet_withdrawals_table.php
 * untuk kenapa satu tabel dipakai untuk pengajar/reseller (menarik
 * Wallet sendiri) maupun Company (menarik Wallet BranchOffice).
 *
 * Alur status (lihat App\Services\Wallet\WalletWithdrawalService):
 *   pending_approval -> approved -> processing -> success
 *                     -> rejected                -> failed
 *                     -> cancelled (dibatalkan sendiri oleh peminta,
 *                        hanya selama masih pending_approval)
 */
class WalletWithdrawal extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wallet_withdrawals';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    // Hasil transfer Duitku belum pasti -- saldo tetap ditahan sampai admin
    // mengecek (WalletWithdrawalService::resolveReview()).
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_LABELS = [
        self::STATUS_PENDING_APPROVAL => 'Menunggu persetujuan',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_PROCESSING => 'Sedang diproses',
        self::STATUS_SUCCESS => 'Berhasil',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_CANCELLED => 'Dibatalkan',
        self::STATUS_NEEDS_REVIEW => 'Sedang dicek',
    ];

    protected $fillable = [
        'wallet_id',
        'requested_by',
        'company_id',
        'branch_office_id',
        'bank_account_id',
        'amount',
        'fee_amount',
        'net_amount',
        'bank_code',
        'bank_account',
        'account_name',
        'purpose',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'duitku_disburse_id',
        'duitku_cust_ref_number',
        'duitku_response',
        'processed_at',
        'failure_reason',
        'held_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'duitku_response' => 'array',
        'processed_at' => 'datetime',
        'held_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    /** Rekening terdaftar yang dipakai (hanya tarik saldo pribadi). */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Jumlah yang dikirim ke rekening: amount (saldo yang dipotong) dikurangi
     * biaya. Baris lama tanpa net_amount = amount utuh.
     */
    public function transferAmount(): float
    {
        return (float) ($this->net_amount ?? $this->amount);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Perlu dicek admin: hasil transfer belum pasti, atau proses berhenti di
     * tengah jalan (disetujui/diproses tapi tidak ada kabar lebih dari 15 menit).
     */
    public function needsReview(): bool
    {
        return $this->status === self::STATUS_NEEDS_REVIEW
            || (in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PROCESSING], true) && $this->updated_at?->lt(now()->subMinutes(15)));
    }

    /** Query pasangan needsReview(). */
    public function scopeNeedingReview(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('status', self::STATUS_NEEDS_REVIEW)
            ->orWhere(fn (Builder $stuck) => $stuck->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PROCESSING])
                ->where('updated_at', '<', now()->subMinutes(15))));
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /** "Wallet Branch <nama>" atau "Wallet pribadi <nama user>" -- label sumber dana, dipakai di daftar approval lintas company. */
    public function sourceLabel(): string
    {
        if ($this->branch_office_id) {
            return 'Branch: '.($this->branchOffice?->name ?? '-');
        }

        return 'Pribadi: '.($this->wallet?->user?->name ?? '-');
    }
}
