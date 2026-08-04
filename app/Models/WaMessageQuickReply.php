<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A canned response ("Balasan Cepat") an agent can insert into the
 * inbox's message box, either by picking it from a list or (once wired
 * up client-side) typing its `shortcut`.
 */
class WaMessageQuickReply extends Model
{
    protected $table = 'wa_message_quick_replies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'device_id',
        'title',
        'shortcut',
        'category',
        'message_content',
        'status',
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
