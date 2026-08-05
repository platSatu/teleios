<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A company-defined label (e.g. "Prospek", "VIP", "Sudah Bayar") a
 * company can tag onto individual chats from the Inbox detail panel —
 * see App\Models\WaChatLabelAssignment for the tagging itself, and
 * App\Http\Controllers\Chat\ChatLabelController for managing the
 * catalog (Chat > Pengaturan > Label).
 */
class WaChatLabel extends Model
{
    protected $table = 'wa_chat_labels';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'color',
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

    public function assignments()
    {
        return $this->hasMany(WaChatLabelAssignment::class);
    }
}
