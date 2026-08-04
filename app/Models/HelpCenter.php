<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One support ticket. See the create_help_centers_table migration for
 * the full field rundown. number_ticket is generated once, here, rather
 * than left to the controller — every path that creates a HelpCenter
 * (superadmin CRUD, user self-service) gets the same guaranteed-unique
 * scheme for free instead of each caller having to remember to set it.
 */
class HelpCenter extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'help_centers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'number_ticket',
        'user_id',
        'category_help_centers_id',
        'name',
        'description',
        'attachment',
        'open_date',
        'close_date',
        'status',
    ];

    protected $casts = [
        'open_date' => 'datetime',
        'close_date' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->number_ticket)) {
                $model->number_ticket = self::generateNumberTicket();
            }

            if (empty($model->open_date)) {
                $model->open_date = now();
            }

            if (empty($model->status)) {
                $model->status = 'open';
            }
        });
    }

    /**
     * "HP" + today's date (ymd) + 4 random uppercase alphanumeric chars,
     * re-rolled on the rare chance of a collision. Short enough to read
     * over the phone, long enough that a same-day collision practically
     * never happens without needing a dedicated sequence/counter table.
     */
    public static function generateNumberTicket(): string
    {
        do {
            $candidate = 'HP'.now()->format('ymd').strtoupper(Str::random(4));
        } while (self::where('number_ticket', $candidate)->exists());

        return $candidate;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryHelpCenter::class, 'category_help_centers_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(HelpCenterAnswer::class, 'help_centers_id')->orderBy('date_answers');
    }
}
