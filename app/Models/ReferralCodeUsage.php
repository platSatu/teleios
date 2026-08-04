<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per successful use of a ReferralCode at checkout — who used it
 * (used_by_user_id), for which purchase (subscription_id), and what
 * discount it gave. The code's owner/referrer is reached via
 * referralCode->user, not duplicated on this row.
 */
class ReferralCodeUsage extends Model
{
    use HasUuids;

    protected $table = 'referral_code_usages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'referral_code_id',
        'used_by_user_id',
        'subscription_id',
        'discount_percent',
        'commission_amount',
    ];

    protected $casts = [
        'discount_percent' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(ReferralCode::class);
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
