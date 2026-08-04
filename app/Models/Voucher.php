<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Per-user voucher — either created manually by superadmin (Superadmin\
 * VoucherController), or auto-generated as an "activation code" when a
 * user buys a Package (Dashboard\PackageCheckoutController). In the
 * purchase case it's created with status 'pending' and no valid_from/
 * valid_until — those only get stamped in once the user actually redeems
 * it (Dashboard\VoucherRedeemController), based on package.duration.
 */
class Voucher extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'vouchers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'company_id',
        'package_id',
        'subscription_id',
        'kode_voucher',
        'valid_from',
        'valid_until',
        'redeemed_at',
        'status',
        // Stamped by App\Console\Commands\SendPackageExpiryReminders as
        // each expiry-reminder milestone is sent — see that migration's
        // docblock (2026_08_05_090000_add_expiry_reminder_columns_to_vouchers_table).
        'reminder_7d_sent_at',
        'reminder_3d_sent_at',
        'reminder_1d_sent_at',
        'reminder_0d_sent_at',
    ];

    protected $casts = [
        // Was 'date' — that silently rounded every expiry up to
        // midnight regardless of the hour a voucher was actually
        // redeemed. See 2026_07_31_050000_change_vouchers_valid_dates_to_datetime
        // for the matching column-type migration.
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'redeemed_at' => 'datetime',
        'reminder_7d_sent_at' => 'datetime',
        'reminder_3d_sent_at' => 'datetime',
        'reminder_1d_sent_at' => 'datetime',
        'reminder_0d_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * 10-char alphanumeric activation code, re-rolled until unique — same
     * "loop instead of trusting probability" approach as
     * VoucherUser::generateUniqueCode() / ReferralCode::generateUniqueCode().
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (self::where('kode_voucher', $code)->exists());

        return $code;
    }
}
