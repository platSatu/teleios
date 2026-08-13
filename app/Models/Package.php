<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'packages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'category_application_id',
        'name',
        'description',
        'duration',
        'price',
        'status',
    ];

    protected $casts = [
        'duration' => 'integer',
        'price' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryApplication(): BelongsTo
    {
        return $this->belongsTo(CategoryApplication::class);
    }

    /**
     * Numeric usage ceilings this package sets (see App\Models\
     * PackageLimit / App\Services\PackageLimitService) — a package with
     * no rows here is unlimited on every metric.
     */
    public function limits(): HasMany
    {
        return $this->hasMany(PackageLimit::class);
    }
}
