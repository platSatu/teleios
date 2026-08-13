<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM Roadmap Fase 4's "segmen dinamis" — a SAVED FILTER over
 * App\Models\WaCustomer, not a stored list of members. Membership is
 * always computed live by matchingCustomersQuery(), so a customer who
 * starts/stops matching shows up/drops out automatically as their data
 * changes — nothing ever has to "re-sync" a segment.
 *
 * `filters` (cast to array) supports any combination of these keys, all
 * AND-ed together — a key that's absent or null is simply not applied:
 *
 *   tag_id           (uuid)  — has this App\Models\WaCustomerTag attached
 *   deal_stage       (string) — has a App\Models\WaDeal in this stage
 *   branch_office_id (uuid)  — belongs to this branch
 *   no_contact_days  (int)   — last_contacted_at is this many days old
 *                              (or has never been contacted at all)
 *   has_overdue_task (bool)  — has a pending App\Models\WaCustomerTask
 *                              past its due date
 *
 * This exact key set is also what powers
 * App\Models\WaCustomerAutomationRule's 'no_contact_days'/'tag_added'/
 * 'deal_stage_changed' triggers conceptually — a segment is "give me
 * everyone matching X right now", an automation rule is "the moment
 * someone starts matching X, do Y".
 */
class WaCustomerSegment extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wa_customer_segments';

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'filters',
        'created_by',
    ];

    protected $casts = [
        'filters' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Builds (but does not execute) the live query for this segment's
     * members, scoped to the given company as a defense-in-depth check
     * even though $this->company_id should already match.
     */
    public function matchingCustomersQuery(string $companyId): Builder
    {
        $filters = $this->filters ?? [];

        $query = WaCustomer::where('company_id', $companyId);

        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', function (Builder $q) use ($filters) {
                $q->where('wa_customer_tags.id', $filters['tag_id']);
            });
        }

        if (! empty($filters['deal_stage'])) {
            $query->whereHas('deals', function (Builder $q) use ($filters) {
                $q->where('stage', $filters['deal_stage']);
            });
        }

        if (! empty($filters['branch_office_id'])) {
            $query->where('branch_office_id', $filters['branch_office_id']);
        }

        if (! empty($filters['no_contact_days']) && is_numeric($filters['no_contact_days'])) {
            $cutoff = now()->subDays((int) $filters['no_contact_days']);
            $query->where(function (Builder $q) use ($cutoff) {
                $q->whereNull('last_contacted_at')
                    ->orWhere('last_contacted_at', '<=', $cutoff);
            });
        }

        if (! empty($filters['has_overdue_task'])) {
            $query->whereHas('tasks', function (Builder $q) {
                $q->where('status', WaCustomerTask::STATUS_PENDING)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now());
            });
        }

        return $query;
    }
}
