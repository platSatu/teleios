<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One phone number that has opted out of receiving broadcast messages
 * from a company — see the migration's docblock (database/migrations/
 * 2026_08_12_150000_create_wa_opt_outs_table.php) for why this is
 * company-wide rather than per-device. Always managed through
 * App\Services\Chat\BroadcastOptOutService — never created/deleted
 * directly, so "is this number opted out" has exactly one code path to
 * check.
 */
class WaOptOut extends Model
{
    protected $table = 'wa_opt_outs';

    protected $keyType = 'string';

    public $incrementing = false;

    public const SOURCE_WA_REPLY = 'wa_reply';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'company_id',
        'phone',
        'source',
        'note',
        'created_by',
        'opted_out_at',
    ];

    protected $casts = [
        'opted_out_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $optOut) {
            if (empty($optOut->id)) {
                $optOut->id = (string) Str::uuid();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
