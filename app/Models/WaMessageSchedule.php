<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A company's scheduled WhatsApp message ("Pesan terjadwal"). `status`
 * (active|inactive) is the user's own pause/resume toggle on the form.
 *
 * Recurring: fires once a day, at `schedule_time`, on every date from
 * `date_start` to `date_end` inclusive (a "one-shot" schedule is simply
 * one where date_end === date_start). Per-day/per-recipient bookkeeping
 * that used to live here as sent_at/attempts/last_error now lives in
 * App\Models\WaMessageScheduleLog instead — see that model and
 * App\Console\Commands\DispatchDueWaMessageSchedules for why.
 *
 * Multi-recipient: `recipients` is a JSON array of
 * {"type": "phone"|"group"|"user", "value": "..."} entries, picked
 * across the 3 tabs on the create/edit form (raw numbers, WA groups,
 * this company's own users). `message`/`category_schedule` are only
 * used when `use_template` is false — otherwise the body is resolved
 * live from `waMessageTemplate->template` at send time.
 */
class WaMessageSchedule extends Model
{
    protected $table = 'wa_message_schedules';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'device_id',
        'title',
        'type',
        'category_schedule',
        'message',
        'link',
        'attachment_path',
        'attachment_type',
        'attachment_original_name',
        'attachment_size',
        'use_template',
        'wa_message_template_id',
        'recipients',
        'date_start',
        'date_end',
        'schedule_time',
        'status',
    ];

    protected $casts = [
        'use_template' => 'boolean',
        'recipients' => 'array',
        'date_start' => 'date',
        'date_end' => 'date',
        'attachment_size' => 'integer',
    ];

    /**
     * True once `schedule_time` on today's date has arrived AND today
     * falls within [date_start, date_end] — the same "due" test
     * DispatchDueWaMessageSchedules uses in SQL, exposed here so the
     * Blade list can show "Terlambat"-style hints without duplicating
     * the date-math.
     */
    public function isDue(): bool
    {
        return $this->isActiveOn(now()->toDateString()) && $this->dueAt(now()->toDateString())->isPast();
    }

    public function isActiveOn(string $date): bool
    {
        return $date >= $this->date_start->toDateString()
            && $date <= $this->date_end->toDateString();
    }

    public function dueAt(string $date): Carbon
    {
        return Carbon::parse($date.' '.$this->schedule_time);
    }

    /**
     * "phone:6281234567890" style keys for every recipient on this
     * schedule — the exact string stored in
     * WaMessageScheduleLog::recipient_key, so the dispatcher/job and the
     * history page both derive it from this one place instead of
     * re-implementing the "{$type}:{$value}" format separately.
     */
    public function recipientKeys(): array
    {
        return collect($this->recipients ?? [])
            ->map(fn (array $r) => ($r['type'] ?? '').':'.($r['value'] ?? ''))
            ->filter(fn (string $key) => $key !== ':')
            ->values()
            ->all();
    }

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

    public function waMessageTemplate()
    {
        return $this->belongsTo(WaMessageTemplate::class);
    }

    public function logs()
    {
        return $this->hasMany(WaMessageScheduleLog::class);
    }

    /**
     * Only meaningful for `type = 'drip'` — see the migration that added
     * `type` and App\Models\WaMessageScheduleStep.
     */
    public function steps()
    {
        return $this->hasMany(WaMessageScheduleStep::class)->orderBy('sequence_order');
    }
}
