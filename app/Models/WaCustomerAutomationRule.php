<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * CRM Roadmap Fase 4 — "when X happens to a customer, do Y". Every
 * trigger is evaluated and every action executed exclusively through
 * App\Services\Crm\CustomerAutomationService, never inline in a
 * controller — same "one owner for the whole mechanism" convention
 * App\Services\Chat\ConversationService already set for wa_conversations.
 *
 * See the migration's docblock for trigger_config/action_config's exact
 * shape per trigger_type.
 */
class WaCustomerAutomationRule extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wa_customer_automation_rules';

    public const TRIGGER_DEAL_STAGE_CHANGED = 'deal_stage_changed';

    public const TRIGGER_TAG_ADDED = 'tag_added';

    public const TRIGGER_NO_CONTACT_DAYS = 'no_contact_days';

    public const TRIGGER_TYPES = [
        self::TRIGGER_DEAL_STAGE_CHANGED,
        self::TRIGGER_TAG_ADDED,
        self::TRIGGER_NO_CONTACT_DAYS,
    ];

    public const TRIGGER_LABELS = [
        self::TRIGGER_DEAL_STAGE_CHANGED => 'Deal pindah ke tahap tertentu',
        self::TRIGGER_TAG_ADDED => 'Tag tertentu ditambahkan',
        self::TRIGGER_NO_CONTACT_DAYS => 'Tidak ada kontak selama N hari',
    ];

    /** The only action this ships with — see the migration's docblock for why. */
    public const ACTION_CREATE_TASK = 'create_task';

    protected $fillable = [
        'company_id',
        'name',
        'trigger_type',
        'trigger_config',
        'action_type',
        'action_config',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'trigger_config' => 'array',
        'action_config' => 'array',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs()
    {
        return $this->hasMany(WaCustomerAutomationLog::class, 'wa_customer_automation_rule_id');
    }
}
