<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One (recipient, calendar day, step) send attempt for a WaMessageSchedule
 * — see the migration that creates this table for why it replaced the
 * old sent_at/attempts/last_error columns on WaMessageSchedule itself.
 * `step_order` is 0 for 'once'/'recurring' schedules (single content, no
 * steps) and matches a WaMessageScheduleStep::sequence_order for 'drip'
 * schedules, which can send the same recipient several different
 * messages on several different days. Written by App\Console\Commands\
 * DispatchDueWaMessageSchedules (creates the 'pending' row the moment a
 * recipient/step becomes due, so it's never picked up twice) and
 * App\Jobs\SendScheduledWaMessage (flips it to 'sent'/'failed').
 */
class WaMessageScheduleLog extends Model
{
    protected $table = 'wa_message_schedule_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'wa_message_schedule_id',
        'recipient_key',
        'step_order',
        'send_date',
        'status',
        'error',
        'sent_at',
        'attempts',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'send_date' => 'date',
        'sent_at' => 'datetime',
        'attempts' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $log) {
            if (empty($log->id)) {
                $log->id = (string) Str::uuid();
            }
        });
    }

    public function schedule()
    {
        return $this->belongsTo(WaMessageSchedule::class, 'wa_message_schedule_id');
    }

    /**
     * "6281234567890" from "phone:6281234567890" — the type/value split
     * used both when resolving a send target (SendScheduledWaMessage)
     * and when rendering a human-readable label on the history page.
     */
    public function recipientType(): string
    {
        return Str::before($this->recipient_key, ':');
    }

    public function recipientValue(): string
    {
        return Str::after($this->recipient_key, ':');
    }
}
