<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a ticket's reply thread — see HelpCenter::answers()
 * and the create_help_center_answers_table migration docblock for why
 * this is "one row per message" rather than a single text column.
 */
class HelpCenterAnswer extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'help_center_answers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'help_centers_id',
        'user_id',
        'answers',
        'status',
        'date_answers',
    ];

    protected $casts = [
        'date_answers' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->date_answers)) {
                $model->date_answers = now();
            }

            if (empty($model->status)) {
                $model->status = 'active';
            }
        });
    }

    public function helpCenter(): BelongsTo
    {
        return $this->belongsTo(HelpCenter::class, 'help_centers_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
