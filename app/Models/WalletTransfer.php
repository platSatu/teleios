<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One completed wallet-to-wallet transfer between two users — see
 * migration comment for why this exists alongside the generic
 * LedgerEntry rows. Written by Dashboard\WalletTransferController::store().
 */
class WalletTransfer extends Model
{
    use HasUuids;

    protected $table = 'wallet_transfers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'sender_user_id',
        'sender_wallet_id',
        'receiver_user_id',
        'receiver_wallet_id',
        'amount',
        'note',
        'sender_balance_before',
        'sender_balance_after',
        'receiver_balance_before',
        'receiver_balance_after',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sender_balance_before' => 'decimal:2',
        'sender_balance_after' => 'decimal:2',
        'receiver_balance_before' => 'decimal:2',
        'receiver_balance_after' => 'decimal:2',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }
}
