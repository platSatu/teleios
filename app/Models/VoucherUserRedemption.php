<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per successful redemption of a shared/promo VoucherUser code at
 * checkout — see Dashboard\PackageCheckoutController::validatePromo() for
 * how `limit` (total across everyone) and `use_by_user` (per this one
 * user) are enforced by counting rows here.
 */
class VoucherUserRedemption extends Model
{
    use HasUuids;

    protected $table = 'voucher_user_redemptions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'voucher_user_id',
        'user_id',
        'subscription_id',
    ];

    public function voucherUser(): BelongsTo
    {
        return $this->belongsTo(VoucherUser::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
