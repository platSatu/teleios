<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One FAQ entry (question in `name`, answer in `descriptions`) for the
 * (future) public web site. Superadmin-managed —
 * App\Http\Controllers\Superadmin\Web\FaqController.
 */
class WebFaq extends Model
{
    protected $table = 'web_faqs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'descriptions',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
