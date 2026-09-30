<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekening pencairan -- lihat migration create_bank_accounts_table dan
 * App\Services\Wallet\BankAccountService (satu-satunya tempat aturannya).
 */
class BankAccount extends Model
{
    use HasUuidPrimaryKey;

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'bank_code',
        'bank_name',
        'account_number',
        'account_number_hash',
        'account_last4',
        'account_name',
        'name_matched',
        'status',
        'active_at',
        'replaced_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = ['account_number', 'account_number_hash'];

    protected $casts = [
        'account_number' => 'encrypted',
        'name_matched' => 'boolean',
        'active_at' => 'datetime',
        'replaced_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Rekening yang saat ini boleh dipakai tarik saldo. */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->where('active_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('replaced_at')->orWhere('replaced_at', '>', now()));
    }

    /** review | rejected | replaced | scheduled | active */
    public function state(): string
    {
        return match (true) {
            $this->status === self::STATUS_PENDING_REVIEW => 'review',
            $this->status === self::STATUS_REJECTED => 'rejected',
            $this->replaced_at && ! $this->replaced_at->isFuture() => 'replaced',
            $this->active_at?->isFuture() ?? true => 'scheduled',
            default => 'active',
        };
    }

    /** "BCA ****1234" */
    public function masked(): string
    {
        return $this->bank_name.' ****'.$this->account_last4;
    }
}
