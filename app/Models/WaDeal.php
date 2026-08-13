<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM Roadmap Fase 3 "Sales Pipeline / Deal" — one sales opportunity
 * tied to a App\Models\WaCustomer, moving through a fixed set of
 * stages. See the migration's docblock for why `stage` is a fixed
 * string enum rather than a configurable-stages table, and
 * App\Http\Controllers\Crm\DealController for the Kanban-style board
 * that manages these.
 */
class WaDeal extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wa_deals';

    public const STAGE_LEAD = 'lead';

    public const STAGE_QUALIFIED = 'qualified';

    public const STAGE_NEGOTIATION = 'negotiation';

    public const STAGE_WON = 'won';

    public const STAGE_LOST = 'lost';

    /** Every stage a deal can be in, board column order. */
    public const STAGES = [
        self::STAGE_LEAD,
        self::STAGE_QUALIFIED,
        self::STAGE_NEGOTIATION,
        self::STAGE_WON,
        self::STAGE_LOST,
    ];

    /** Human labels, same order — used by both the board and the edit form. */
    public const STAGE_LABELS = [
        self::STAGE_LEAD => 'Lead',
        self::STAGE_QUALIFIED => 'Prospek',
        self::STAGE_NEGOTIATION => 'Negosiasi',
        self::STAGE_WON => 'Deal Menang',
        self::STAGE_LOST => 'Deal Kalah',
    ];

    /** Stages that count as the deal being decided — see moveStage(). */
    public const CLOSED_STAGES = [self::STAGE_WON, self::STAGE_LOST];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'wa_customer_id',
        'title',
        'value',
        'stage',
        'expected_close_at',
        'assigned_to',
        'created_by',
        'closed_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'expected_close_at' => 'date',
        'closed_at' => 'datetime',
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

    public function scopeOpenStage(Builder $query): Builder
    {
        return $query->whereNotIn('stage', self::CLOSED_STAGES);
    }

    public function isClosed(): bool
    {
        return in_array($this->stage, self::CLOSED_STAGES, true);
    }
}
