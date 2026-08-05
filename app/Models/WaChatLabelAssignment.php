<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One App\Models\WaChatLabel tagged onto one chat. A "chat" has no row
 * of its own in Laravel's database (conversations live entirely in
 * g_backend's MySQL, reached through App\Services\Chat\InboxService) —
 * so it's identified here the same way the rest of the app already
 * does: the (device_id, chat_jid) pair. See App\Http\Controllers\Chat\
 * InboxController::labels()/attachLabel()/detachLabel().
 */
class WaChatLabelAssignment extends Model
{
    protected $table = 'wa_chat_label_assignments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'wa_chat_label_id',
        'company_id',
        'device_id',
        'chat_jid',
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

    public function label()
    {
        return $this->belongsTo(WaChatLabel::class, 'wa_chat_label_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
