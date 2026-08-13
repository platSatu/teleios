<?php

namespace App\Models;

use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One footer link/block for the public web site (fe-konexa) — flat
 * list, same shape as App\Models\WebHeader/WebFeature. See the
 * create_web_footers_table migration for the full field rundown.
 * Superadmin-managed — App\Http\Controllers\Superadmin\Web\
 * FooterController. Exposed publicly (status = active only, ordered by
 * sort_order) via GET /api/frontend/footers — see
 * App\Http\Controllers\Api\Frontend\FooterController.
 */
class WebFooter extends Model
{
    protected $table = 'web_footers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'background_image',
        'background_color',
        'column_width',
        'name',
        'link',
        'target_blank',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'target_blank' => 'boolean',
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

    public function getBackgroundImageUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->background_image);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
