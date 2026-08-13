<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM Roadmap Fase 2 "Task & Follow-up" — a dated, assignable to-do tied
 * to a App\Models\WaCustomer (not a single chat), so it survives across
 * every device/number that customer writes in from. See the migration's
 * docblock for how this differs from App\Models\WaChatNote (freeform,
 * passive, per-chat) and App\Http\Controllers\Crm\CustomerTaskController
 * for the page that manages these.
 */
class WaCustomerTask extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wa_customer_tasks';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_DONE];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'wa_customer_id',
        'title',
        'description',
        'due_at',
        'assigned_to',
        'created_by',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(WaCustomer::class, 'wa_customer_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());
    }
}
