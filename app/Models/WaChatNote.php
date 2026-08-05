<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One freeform internal note attached to a chat, shown in the Inbox
 * detail panel's NOTES section — same (device_id, chat_jid) pairing
 * App\Models\WaChatLabelAssignment uses, since a "chat" has no row of
 * its own in Laravel's database. Managed entirely from
 * App\Http\Controllers\Chat\InboxController's notes()/addNote()/
 * updateNote()/deleteNote() methods.
 */
class WaChatNote extends Model
{
    protected $table = 'wa_chat_notes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'device_id',
        'chat_jid',
        'created_by',
        'note',
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

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
