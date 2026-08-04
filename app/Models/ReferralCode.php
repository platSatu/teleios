<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One referral code per user (1:1, same shape as Wallet) — auto-created
 * in App\Models\User::boot()'s `created` event right alongside the
 * user's Wallet, so every user has a code from the moment they register.
 * Superadmin can edit the commission `percentage` (default 20%) and
 * `status` (active/blocked) via Superadmin\ReferralCodeController; it's
 * never created "from scratch" through a normal create form, since it's
 * always tied to exactly one existing user.
 */
class ReferralCode extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'referral_codes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'code',
        'percentage',
        'status',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $referralCode) {
            if (empty($referralCode->id)) {
                $referralCode->id = (string) Str::uuid();
            }

            if (empty($referralCode->code)) {
                $name = $referralCode->relationLoaded('user')
                    ? $referralCode->user?->name
                    : User::find($referralCode->user_id)?->name;

                $referralCode->code = self::generateUniqueCode($name);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usages()
    {
        return $this->hasMany(ReferralCodeUsage::class);
    }

    /**
     * First name (letters/digits only, uppercased, capped at 6 chars) +
     * 4 random uppercase chars — re-rolled in a loop until it's actually
     * unique in the table, so this guarantees no duplicates rather than
     * just relying on Str::random() being unlikely to collide.
     */
    public static function generateUniqueCode(?string $name): string
    {
        $firstName = trim(strtok((string) $name, ' '));
        $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $firstName) ?: 'USER');
        $base = substr($base, 0, 6) ?: 'USER';

        do {
            $code = $base . strtoupper(Str::random(4));
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
