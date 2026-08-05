<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AI-responder configuration for one connected device. api_configuration
 * is cast `encrypted` since it holds a tenant's own AI provider API
 * key/config — never stored (or logged) in plain text.
 */
class WaAiBot extends Model
{
    protected $table = 'wa_ai_bots';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'device_id',
        'branch_office_id',
        'ai_provider',
        'ai_model',
        'wa_ai_bot_provider_id',
        'wa_ai_bot_model_id',
        'attach_file_path',
        'attach_file_original_name',
        'knowledge_base_text',
        'api_configuration',
        'ai_behaviour_prompt',
        'active_bot_immediately',
        'custom_activation_time',
        'activation_start_at',
        'activation_end_at',
        'status',
        'last_triggered_at',
        'trigger_count',
        'last_error',
    ];

    protected $casts = [
        'active_bot_immediately' => 'boolean',
        'custom_activation_time' => 'boolean',
        'activation_start_at' => 'datetime',
        'activation_end_at' => 'datetime',
        'api_configuration' => 'encrypted',
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function provider()
    {
        return $this->belongsTo(WaAiBotProvider::class, 'wa_ai_bot_provider_id');
    }

    public function model()
    {
        return $this->belongsTo(WaAiBotModel::class, 'wa_ai_bot_model_id');
    }

    /**
     * Whether App\Http\Controllers\Api\WaIncomingMessageWebhookController
     * should let this bot answer an incoming message right now. Mirrors
     * the two-way toggle in resources/views/chat/ai-bots/_form.blade.php:
     *
     *   - status must be 'active' (the hard on/off switch) — everything
     *     else is moot if this is off.
     *   - active_bot_immediately: "always on" once active, no schedule.
     *   - custom_activation_time: only on inside [activation_start_at,
     *     activation_end_at]; if that window is incomplete (one end
     *     missing) it's treated as not configured, i.e. never active,
     *     rather than guessing an open-ended window.
     *
     * If neither toggle is set, the bot is 'active' but not actually
     * switched on to reply to anything — same as a keyword auto-reply
     * rule with status=active but no keyword configured.
     */
    public function isCurrentlyActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->active_bot_immediately) {
            return true;
        }

        if ($this->custom_activation_time && $this->activation_start_at && $this->activation_end_at) {
            $now = now();

            return $now->between($this->activation_start_at, $this->activation_end_at, true);
        }

        return false;
    }
}
