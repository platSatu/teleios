<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalog entry for something a Package can cap — see the migration's
 * docblock for the full reasoning behind `category_application_id` being
 * nullable (reusable across future applications, not just Chat/Konexa)
 * and what `metric_type` controls.
 */
class LimitMetric extends Model
{
    use HasUuids;

    protected $table = 'limit_metrics';

    protected $keyType = 'string';

    public $incrementing = false;

    public const TYPE_CONSUMABLE = 'consumable';

    public const TYPE_STOCK = 'stock';

    protected $fillable = [
        'category_application_id',
        'key',
        'name',
        'description',
        'unit',
        'metric_type',
        'status',
    ];

    public function categoryApplication(): BelongsTo
    {
        return $this->belongsTo(CategoryApplication::class);
    }

    public function packageLimits(): HasMany
    {
        return $this->hasMany(PackageLimit::class);
    }

    public function isConsumable(): bool
    {
        return $this->metric_type === self::TYPE_CONSUMABLE;
    }

    public function isStock(): bool
    {
        return $this->metric_type === self::TYPE_STOCK;
    }
}
