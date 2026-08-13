<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How many of one LimitMetric a given Package allows — see the
 * migration's docblock. A Package with no row here for a metric is
 * unlimited for that metric (App\Services\PackageLimitService::limitFor()
 * returns null, never blocks).
 */
class PackageLimit extends Model
{
    use HasUuids;

    protected $table = 'package_limits';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'package_id',
        'limit_metric_id',
        'max_value',
    ];

    protected $casts = [
        'max_value' => 'integer',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function limitMetric(): BelongsTo
    {
        return $this->belongsTo(LimitMetric::class);
    }
}
