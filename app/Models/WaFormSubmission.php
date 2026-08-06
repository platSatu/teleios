<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One received Google Form response, success or failure — see
 * App\Models\WaFormIntegration and App\Http\Controllers\Api\
 * GoogleFormWebhookController. Write-only from the company's
 * perspective: shown as a read-only recent-activity log on the
 * integration's detail page, never edited.
 */
class WaFormSubmission extends Model
{
    protected $table = 'wa_form_submissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'wa_form_integration_id',
        'payload',
        'target_number',
        'message_sent',
        'status',
        'error_message',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $submission) {
            if (empty($submission->id)) {
                $submission->id = (string) Str::uuid();
            }
        });
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WaFormIntegration::class, 'wa_form_integration_id');
    }
}
