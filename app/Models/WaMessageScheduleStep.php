<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One message in a 'drip' type WaMessageSchedule — what to send
 * (manual message/category_schedule, or use_template + a
 * WaMessageTemplate), in what order (sequence_order), and how many days
 * after the schedule's own date_start this fires (delay_days). Who it
 * sends to and what device it sends from both live on the parent
 * schedule, not here — see App\Console\Commands\
 * DispatchDueWaMessageSchedules for how due steps are found and
 * App\Jobs\SendScheduledWaMessage for how one is actually sent.
 */
class WaMessageScheduleStep extends Model
{
    protected $table = 'wa_message_schedule_steps';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'wa_message_schedule_id',
        'sequence_order',
        'delay_days',
        'use_template',
        'wa_message_template_id',
        'category_schedule',
        'message',
        'status',
    ];

    protected $casts = [
        'sequence_order' => 'integer',
        'delay_days' => 'integer',
        'use_template' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $step) {
            if (empty($step->id)) {
                $step->id = (string) Str::uuid();
            }
        });
    }

    public function schedule()
    {
        return $this->belongsTo(WaMessageSchedule::class, 'wa_message_schedule_id');
    }

    public function waMessageTemplate()
    {
        return $this->belongsTo(WaMessageTemplate::class);
    }

    /**
     * The calendar date this step is due: the parent schedule's
     * date_start plus this step's own delay_days — e.g. date_start=Aug 1,
     * delay_days=3 fires on Aug 4. Mirrors the old WaMessageSequence::
     * dueDate() this replaces.
     */
    public function dueDate(\Illuminate\Support\Carbon $dateStart): \Illuminate\Support\Carbon
    {
        return $dateStart->copy()->addDays($this->delay_days);
    }
}
