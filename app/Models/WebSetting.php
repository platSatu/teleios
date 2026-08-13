<?php

namespace App\Models;

use App\Helpers\WebImageUploader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Singleton settings row — site-wide favicon/logo, meta tags, contact
 * info, and Google Tag Manager/Analytics/Maps integration, consumed by
 * fe-konexa's public frontend (see App\Http\Controllers\Api\Frontend\
 * WebSettingController). Same singleton shape as
 * App\Models\AiModerationSetting — always accessed through current(),
 * never queried directly, so exactly one row ever exists.
 */
class WebSetting extends Model
{
    protected $table = 'web_settings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'favicon',
        'logo',
        'meta_description',
        'meta_keywords',
        'meta_images',
        'handphone',
        'email',
        'address',
        'google_tag',
        'google_analytics',
        'gmaps',
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

    public function getFaviconUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->favicon);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->logo);
    }

    public function getMetaImagesUrlAttribute(): ?string
    {
        return WebImageUploader::url($this->meta_images);
    }

    /**
     * The one row this table ever needs — created with all-null
     * defaults on first access rather than requiring a seeder. Every
     * caller (a future settings form, WebSettingController) goes
     * through this instead of querying the table directly.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::create();
    }
}
