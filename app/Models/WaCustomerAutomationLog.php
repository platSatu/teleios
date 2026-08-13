<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per time a App\Models\WaCustomerAutomationRule actually fired
 * for a specific App\Models\WaCustomer — see the migration's docblock
 * for the cooldown-guard + audit-trail double duty this serves. Only
 * ever written by App\Services\Crm\CustomerAutomationService.
 */
class WaCustomerAutomationLog extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'wa_customer_automation_logs';

    /** No updated_at column — a log row is never edited after it's written. */
    public $timestamps = false;

    protected $fillable = [
        'wa_customer_automation_rule_id',
        'wa_customer_id',
        'fired_at',
    ];

    protected $casts = [
        'fired_at' => 'datetime',
    ];

    public function rule()
    {
        return $this->belongsTo(WaCustomerAutomationRule::class, 'wa_customer_automation_rule_id');
    }

    public function customer()
    {
        return $this->belongsTo(WaCustomer::class, 'wa_customer_id');
    }
}
