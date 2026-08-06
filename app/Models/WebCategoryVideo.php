<?php

namespace App\Models;

use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One video category/section shown on the (future) public web site. See
 * the create_web_category_videos_table migration for the full field
 * rundown. Superadmin-managed —
 * App\Http\Controllers\Superadmin\Web\CategoryVideoController.
 */
class WebCategoryVideo extends Model
{
    protected $table = 'web_category_videos';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'thumbnail',
        'description',
        'date_publish',
        'status',
    ];

    protected $casts = [
        'date_publish' => 'datetime',
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

    public function getThumbnailUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->thumbnail);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(WebVideo::class, 'web_category_video_id');
    }
}
