<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A one-off reminder message ("Pengingat") sent at start_reminder, to
 * either phone_number or a WhatsApp group (is_group toggle).
 */
class WaMessageReminder extends Model
{
    protected $table = 'wa_message_reminders';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'device_id',
        'category_message_reminder',
        'title_reminder',
        'message',
        'start_reminder',
        'is_group',
        'group_jid',
        'phone_number',
        'status',
    ];

    protected $casts = [
        'is_group' => 'boolean',
        'start_reminder' => 'datetime',
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
}
