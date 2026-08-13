<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One node in a App\Models\WaChatbotFlow's conversation tree. See the
 * 2026_08_12_180100_create_wa_chatbot_flow_steps_table.php migration's
 * docblock for what each step_type/ACTION_* does, and
 * App\Services\Chat\ChatbotFlowService::walk() for how they're executed.
 */
class WaChatbotFlowStep extends Model
{
    protected $table = 'wa_chatbot_flow_steps';

    protected $keyType = 'string';

    public $incrementing = false;

    public const TYPE_MESSAGE = 'message';

    public const TYPE_CHOICE = 'choice';

    public const TYPE_ACTION = 'action';

    public const TYPE_END = 'end';

    public const TYPES = [self::TYPE_MESSAGE, self::TYPE_CHOICE, self::TYPE_ACTION, self::TYPE_END];

    /** Assigns the conversation (action_value = a specific user id, or null for auto-assign). */
    public const ACTION_ASSIGN_CONVERSATION = 'assign_conversation';

    /** Flips App\Models\WaConversation::status to 'pending' (waiting on an agent). */
    public const ACTION_SET_STATUS_PENDING = 'set_status_pending';

    /** Flips App\Models\WaConversation::status to 'resolved'. */
    public const ACTION_SET_STATUS_RESOLVED = 'set_status_resolved';

    /** Tags the chat with a App\Models\WaChatLabel (action_value = wa_chat_labels.id). */
    public const ACTION_ADD_LABEL = 'add_label';

    /**
     * Marks the conversation pending (an agent needs to act) AND
     * unconditionally ends the flow session regardless of
     * default_next_step_id — see ChatbotFlowService::walk(). Once a
     * customer is hand off to a human, continuing to auto-advance through
     * more bot steps would talk over whatever the agent is about to say.
     */
    public const ACTION_HANDOFF_HUMAN = 'handoff_human';

    public const ACTIONS = [
        self::ACTION_ASSIGN_CONVERSATION,
        self::ACTION_SET_STATUS_PENDING,
        self::ACTION_SET_STATUS_RESOLVED,
        self::ACTION_ADD_LABEL,
        self::ACTION_HANDOFF_HUMAN,
    ];

    protected $fillable = [
        'wa_chatbot_flow_id',
        'step_type',
        'message',
        'options',
        'action',
        'action_value',
        'default_next_step_id',
        'is_start',
        'position',
    ];

    protected $casts = [
        'options' => 'array',
        'is_start' => 'boolean',
        'position' => 'integer',
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

    public function defaultNextStep()
    {
        return $this->belongsTo(self::class, 'default_next_step_id');
    }
}
