<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One running usage counter — see the migration's docblock for why it's
 * scoped to (company, branch office, metric, subscription) and why it
 * only resets on a new subscription rather than a fixed calendar month.
 * Written/read exclusively through App\Services\PackageLimitService;
 * nothing else should touch `used_value` directly.
 */
class CompanyLimitUsage extends Model
{
    use HasUuids;

    protected $table = 'company_limit_usages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'limit_metric_id',
        'subscription_id',
        'used_value',
        'period_start',
        'period_end',
        'notified_at',
    ];

    protected $casts = [
        'used_value' => 'integer',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function limitMetric(): BelongsTo
    {
        return $this->belongsTo(LimitMetric::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
