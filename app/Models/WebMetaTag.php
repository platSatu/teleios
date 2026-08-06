<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One reusable SEO meta tag entry a superadmin can attach to public web
 * content (articles, videos, ...). See the create_web_meta_tags_table
 * migration for the full field rundown. Superadmin-managed —
 * App\Http\Controllers\Superadmin\Web\MetaTagController.
 */
class WebMetaTag extends Model
{
    protected $table = 'web_meta_tags';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
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
