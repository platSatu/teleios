<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One CSAT (Customer Satisfaction) poll sent to an end customer — Fitur
 * #7. See the 2026_08_12_190100_create_wa_csat_surveys_table.php
 * migration's docblock for the full lifecycle: created by
 * App\Services\Chat\CsatSurveyService/App\Jobs\SendCsatSurvey the moment
 * a conversation is resolved, filled in later by App\Http\Controllers\
 * Api\WaPollVoteWebhookController once the customer actually votes.
 */
class WaCsatSurvey extends Model
{
    protected $table = 'wa_csat_surveys';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'device_id',
        'chat_jid',
        'wa_conversation_id',
        'poll_message_id',
        'question',
        'options',
        'sent_at',
        'score',
        'selected_option',
        'responded_at',
    ];

    protected $casts = [
        'options' => 'array',
        'sent_at' => 'datetime',
        'score' => 'integer',
        'responded_at' => 'datetime',
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

    public function conversation()
    {
        return $this->belongsTo(WaConversation::class, 'wa_conversation_id');
    }
}
