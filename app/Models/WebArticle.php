<?php

namespace App\Models;

use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * One article under a App\Models\WebCategoryArticle section. See the
 * create_web_articles_table migration for the full field rundown.
 * Superadmin-managed — App\Http\Controllers\Superadmin\Web\ArticleController.
 */
class WebArticle extends Model
{
    protected $table = 'web_articles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'web_category_article_id',
        'user_id',
        'title',
        'slug',
        'description',
        'images',
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

    /**
     * Full public URL for `images` — see App\Helpers\WebImageUploader.
     */
    public function getImagesUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->images);
    }

    /**
     * Full public URL for the Open Graph / share image — falls back to
     * the article's own `images` when `meta_images` was left empty, so
     * the public page's og:image never has to fall back to nothing.
     */
    public function getMetaImagesUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->meta_images ?: $this->images);
    }

    /**
     * Effective <meta name="description"> text — falls back to
     * `description` when `meta_descriptions` was left empty.
     */
    public function getEffectiveMetaDescriptionAttribute(): ?string
    {
        return $this->meta_descriptions ?: $this->description;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WebCategoryArticle::class, 'web_category_article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function metaTags(): BelongsToMany
    {
        return $this->belongsToMany(WebMetaTag::class, 'web_article_meta_tag');
    }
}
