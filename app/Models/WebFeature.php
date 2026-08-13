<?php

namespace App\Models;

use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One highlighted product/service feature (name, description, image)
 * shown on the (future) public web site — flat list, no category, same
 * shape as App\Models\WebFaq. Exposed publicly (status = active only)
 * via GET /api/frontend/features — see App\Http\Controllers\Api\
 * Frontend\FeatureController.
 */
class WebFeature extends Model
{
    protected $table = 'web_features';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'images',
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

    public function getImagesUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->images);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
