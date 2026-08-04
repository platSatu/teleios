<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Generic key-value store for global settings — currently just the
 * purchase cashback/point rule (point_amount_threshold, point_value,
 * point_enabled), editable by superadmin via Superadmin\
 * PointSettingController. Cached briefly since get() is read on every
 * package purchase.
 */
class Setting extends Model
{
    use HasUuids;

    protected $table = 'settings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
    ];

    private const CACHE_TTL = 60;

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("setting:{$key}", self::CACHE_TTL, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget("setting:{$key}");
    }
}
