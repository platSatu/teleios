<?php

namespace App\Models;

use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One footer link/block for the public web site (fe-konexa) — flat
 * list, same shape as App\Models\WebHeader/WebFeature, EXCEPT several
 * rows can share the same `group_name` (nullable) so fe-konexa can
 * render them together under one column header (e.g. "Support", "About")
 * instead of one flat row of links — see App\View\Composers\
 * FooterComposer on the fe-konexa side for the actual grouping. See the
 * create_web_footers_table / add_group_name_to_web_footers_table
 * migrations for the full field rundown. Superadmin-managed —
 * App\Http\Controllers\Superadmin\Web\FooterController. Exposed publicly
 * (status = active only, ordered by sort_order) via GET
 * /api/frontend/footers — see App\Http\Controllers\Api\Frontend\
 * FooterController.
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
        'group_name',
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
