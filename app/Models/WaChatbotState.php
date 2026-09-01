<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Which step of which App\Models\WaChatbotFlow one (device_id, chat_jid)
 * is currently sitting at, waiting for the customer's next reply. See the
 * 2026_08_12_180200_create_wa_chatbot_states_table.php migration's
 * docblock and App\Services\Chat\ChatbotFlowService, the only writer of
 * this table.
 */
class WaChatbotState extends Model
{
    protected $table = 'wa_chatbot_states';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'device_id',
        'chat_jid',
        'sender_phone',
        'wa_chatbot_flow_id',
        'current_step_id',
        'variables',
        'started_at',
        'last_interaction_at',
    ];

    protected $casts = [
        'variables' => 'array',
        'started_at' => 'datetime',
        'last_interaction_at' => 'datetime',
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

    public function flow()
    {
        return $this->belongsTo(WaChatbotFlow::class, 'wa_chatbot_flow_id');
    }

    public function currentStep()
    {
        return $this->belongsTo(WaChatbotFlowStep::class, 'current_step_id');
    }
}
