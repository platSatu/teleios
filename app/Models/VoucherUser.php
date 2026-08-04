<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A shared/promo-style voucher code: unlike App\Models\Voucher (which is
 * always tied 1:1 to a specific user), a VoucherUser code is not owned by
 * any single user — it's a code any user can redeem, up to `limit` times
 * in total and up to `use_by_user` times per individual user.
 */
class VoucherUser extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'voucher_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'percentase',
        'kode_voucher',
        'limit',
        'use_by_user',
        'valid_from',
        'valid_until',
        'status',
    ];

    protected $casts = [
        'percentase' => 'decimal:2',
        'limit' => 'integer',
        'use_by_user' => 'integer',
        // Was 'date' — Dashboard\PackageCheckoutController::validatePromo()
        // compared this against Carbon::today() (date-only), so a promo
        // code always stayed valid through the entire expiry day instead
        // of expiring at the actual configured hour. See
        // 2026_07_31_050100_change_voucher_users_valid_dates_to_datetime.
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $voucherUser) {
            if (empty($voucherUser->id)) {
                $voucherUser->id = (string) Str::uuid();
            }

            if (empty($voucherUser->kode_voucher)) {
                $voucherUser->kode_voucher = self::generateUniqueCode();
            }
        });
    }

    /**
     * 6-digit numeric code, re-rolled until it's actually unique in the
     * table (a plain random draw can collide, so this loops instead of
     * trusting probability).
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('kode_voucher', $code)->exists());

        return $code;
    }

    public function redemptions()
    {
        return $this->hasMany(VoucherUserRedemption::class);
    }
}
