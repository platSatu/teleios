<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
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

    protected $fillable = [
        'wallet_id',
        'requested_by',
        'company_id',
        'branch_office_id',
        'amount',
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
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'duitku_response' => 'array',
        'processed_at' => 'datetime',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
