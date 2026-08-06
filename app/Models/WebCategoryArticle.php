<?php

namespace App\Models;

use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One article category/section (e.g. "Tips", "Rilis Produk") shown on
 * the (future) public web/blog. See the create_web_category_articles_table
 * migration for the full field rundown. Superadmin-managed —
 * App\Http\Controllers\Superadmin\Web\CategoryArticleController.
 */
class WebCategoryArticle extends Model
{
    protected $table = 'web_category_articles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'images',
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

    /**
     * Full public URL for `images` — see App\Helpers\WebImageUploader.
     * Accessor, not stored: keeps the DB column a plain relative path
     * (portable across environments/domains) while Blade just calls
     * ->images_url.
     */
    public function getImagesUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->images);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(WebArticle::class, 'web_category_article_id');
    }
}
