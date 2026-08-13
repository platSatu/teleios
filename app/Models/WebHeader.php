<?php

namespace App\Models;

use App\Helpers\WebFileUploader;
use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One hero/header slide for the (future) public web site homepage. See
 * the create_web_headers_table migration for the full field rundown —
 * in particular background_type, which decides whether `videos` or
 * `background_images` is the slide's actual background. Superadmin-
 * managed — App\Http\Controllers\Superadmin\Web\HeaderController.
 * Exposed publicly (status = active only, ordered by sort_order) via
 * GET /api/frontend/headers — see App\Http\Controllers\Api\Frontend\
 * HeaderController.
 */
class WebHeader extends Model
{
    protected $table = 'web_headers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'background_type',
        'videos',
        'background_images',
        'thumbnail_images',
        'thumbnail_background_images',
        'text',
        'descriptions',
        'color_headline',
        'color_description',
        'button_action',
        'button_text',
        'button_link',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
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

    /**
     * Full public URL for the uploaded video file (`videos`) — only
     * meaningful when background_type = video.
     */
    public function getVideosUrlAttribute(): ?string
    {
        return WebFileUploader::url($this->videos);
    }

    public function getBackgroundImagesUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->background_images);
    }

    public function getThumbnailImagesUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->thumbnail_images);
    }

    /**
     * Thumbnail/preview khusus untuk slide bertipe Gambar — pasangan
     * background_images, seperti thumbnail_images adalah pasangan
     * videos.
     */
    public function getThumbnailBackgroundImagesUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->thumbnail_background_images);
    }

    /**
     * True when this slide's background is the uploaded video
     * (background_type = video) rather than background_images.
     */
    public function isVideoBackground(): bool
    {
        return $this->background_type === 'video';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
