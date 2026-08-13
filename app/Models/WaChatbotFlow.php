<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A multi-step chatbot conversation tree — Fitur #6. See the
 * 2026_08_12_180000_create_wa_chatbot_flows_table.php migration's
 * docblock for the full design rationale (how this relates to the
 * simpler App\Models\WaMessageAutoReply, and the overall step model).
 */
class WaChatbotFlow extends Model
{
    protected $table = 'wa_chatbot_flows';

    protected $keyType = 'string';

    public $incrementing = false;

    /** Used when a flow hasn't set its own session_timeout_minutes. */
    public const DEFAULT_SESSION_TIMEOUT_MINUTES = 30;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'device_id',
        'name',
        'trigger_keyword',
        'trigger_match_type',
        'status',
        'session_timeout_minutes',
    ];

    protected $casts = [
        'session_timeout_minutes' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * True if `body` should start this flow. Deliberately no `is_default`
     * fallback concept the way WaMessageAutoReply has one — a flow is
     * only ever entered through an explicit, intentional trigger; being
     * accidentally dropped into a multi-step flow by a generic fallback
     * would be a much more confusing experience than getting a
     * one-shot default reply.
     */
    public function matchesTrigger(string $body): bool
    {
        if (! $this->trigger_keyword) {
            return false;
        }

        return match ($this->trigger_match_type) {
            'exact' => mb_strtolower(trim($body)) === mb_strtolower(trim($this->trigger_keyword)),
            default => mb_stripos($body, $this->trigger_keyword) !== false, // 'contains'
        };
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function steps()
    {
        return $this->hasMany(WaChatbotFlowStep::class)->orderBy('position');
    }

    public function states()
    {
        return $this->hasMany(WaChatbotState::class);
    }
}
