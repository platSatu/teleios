<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One WhatsApp thread's current "chat ops" state — status, who's
 * working it, and its SLA timers. See the migration's docblock
 * (database/migrations/2026_08_12_130000_create_wa_conversations_table.php)
 * for how this differs from App\Models\WaContact. Always managed
 * through App\Services\Chat\ConversationService — never created/updated
 * directly from a controller, so the status machine and SLA math stay
 * in one place.
 */
class WaConversation extends Model
{
    protected $table = 'wa_conversations';

    protected $keyType = 'string';

    public $incrementing = false;

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RESOLVED = 'resolved';

    /** Every value status is allowed to hold — used to validate input. */
    public const STATUSES = [self::STATUS_OPEN, self::STATUS_PENDING, self::STATUS_RESOLVED];

    /** status values that still count against SLA / show up in "active queue" views. */
    public const ACTIVE_STATUSES = [self::STATUS_OPEN, self::STATUS_PENDING];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'device_id',
        'chat_jid',
        'contact_id',
        'status',
        'assigned_to',
        'assigned_at',
        'opened_at',
        'first_response_at',
        'resolved_at',
        'last_inbound_at',
        'last_outbound_at',
        'sla_first_response_due_at',
        'sla_resolution_due_at',
        'first_response_breached',
        'resolution_breached',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'opened_at' => 'datetime',
        'first_response_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'last_outbound_at' => 'datetime',
        'sla_first_response_due_at' => 'datetime',
        'sla_resolution_due_at' => 'datetime',
        'first_response_breached' => 'boolean',
        'resolution_breached' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $conversation) {
            if (empty($conversation->id)) {
                $conversation->id = (string) Str::uuid();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function contact()
    {
        return $this->belongsTo(WaContact::class, 'contact_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeForCompany(Builder $query, string $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function scopeAssignedTo(Builder $query, string $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }
}
