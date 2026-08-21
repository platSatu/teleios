<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One company's custom wording override for one Jadwal message type —
 * see App\Services\Jadwal\JadwalMessageTemplateService, which is the
 * only place that should ever read/write this (never inline elsewhere).
 */
class JadwalMessageTemplate extends Model
{
    protected $table = 'jadwal_message_templates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'message_key',
        'body',
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
