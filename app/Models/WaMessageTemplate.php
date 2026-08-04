<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A company's reusable WhatsApp message template ("WA Template" under
 * Chat > Pengaturan > Pesan). Picked from App\Models\WaMessageSchedule
 * via `use_template` + `wa_message_template_id` — deliberately a live
 * reference rather than copying `template` into the schedule's own
 * `message` column at save time, so editing a template here also
 * updates every future occurrence of any recurring schedule that still
 * points at it (see App\Jobs\SendScheduledWaMessage).
 */
class WaMessageTemplate extends Model
{
    protected $table = 'wa_message_templates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'template',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $template) {
            if (empty($template->id)) {
                $template->id = (string) Str::uuid();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function schedules()
    {
        return $this->hasMany(WaMessageSchedule::class, 'wa_message_template_id');
    }
}
