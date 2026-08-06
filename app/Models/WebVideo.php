<?php

namespace App\Models;

use App\Helpers\WebFileUploader;
use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * One video under a App\Models\WebCategoryVideo section. See the
 * create_web_videos_table migration for the full field rundown.
 * Superadmin-managed — App\Http\Controllers\Superadmin\Web\VideoController.
 */
class WebVideo extends Model
{
    protected $table = 'web_videos';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'web_category_video_id',
        'user_id',
        'title',
        'slug',
        'thumbnail',
        'description',
        'videos',
        'link_youtube',
        'meta_keywords',
        'meta_descriptions',
        'meta_images',
        'count_read',
        'date_publish',
        'status',
    ];

    protected $casts = [
        'date_publish' => 'datetime',
        'count_read' => 'integer',
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

    /**
     * Full public URL for the uploaded video file (`videos`) — null if
     * this entry only has a link_youtube instead.
     */
    public function getVideosUrlAttribute(): ?string
    {
        return WebFileUploader::url($this->videos);
    }

    /**
     * Full public URL for the Open Graph / share image — falls back to
     * `thumbnail` when `meta_images` was left empty.
     */
    public function getMetaImagesUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->meta_images ?: $this->thumbnail);
    }

    public function getEffectiveMetaDescriptionAttribute(): ?string
    {
        return $this->meta_descriptions ?: $this->description;
    }

    /**
     * Normalizes link_youtube (a watch/shorts/short youtu.be link, or
     * already an embed link) into an https://www.youtube.com/embed/...
     * URL suitable for an <iframe src>. Returns null if link_youtube is
     * empty, or the original value unchanged if the video id can't be
     * parsed out of it (so a malformed link still shows *something*
     * rather than silently disappearing).
     */
    public function getYoutubeEmbedUrlAttribute(): ?string
    {
        if (! $this->link_youtube) {
            return null;
        }

        $url = trim($this->link_youtube);

        if (str_contains($url, 'youtube.com/embed/')) {
            return $url;
        }

        $videoId = null;

        if (preg_match('#youtu\.be/([A-Za-z0-9_-]{6,})#', $url, $matches)) {
            $videoId = $matches[1];
        } elseif (preg_match('#youtube\.com/shorts/([A-Za-z0-9_-]{6,})#', $url, $matches)) {
            $videoId = $matches[1];
        } elseif (preg_match('#[?&]v=([A-Za-z0-9_-]{6,})#', $url, $matches)) {
            $videoId = $matches[1];
        }

        return $videoId ? "https://www.youtube.com/embed/{$videoId}" : $url;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WebCategoryVideo::class, 'web_category_video_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function metaTags(): BelongsToMany
    {
        return $this->belongsToMany(WebMetaTag::class, 'web_video_meta_tag');
    }
}
