<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Superadmin-managed catalog of AI providers offered on the "AI Bot" tab
 * (App\Http\Controllers\Chat\AiBotController) — see
 * Superadmin\WaAiBotProviderController. Deliberately minimal (just
 * name + status), same shape as App\Models\CategoryApplication: this
 * table only exists to be picked from a dropdown and to gate which
 * providers are currently offered.
 */
class WaAiBotProvider extends Model
{
    protected $table = 'wa_ai_bot_providers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
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

    public function models()
    {
        return $this->hasMany(WaAiBotModel::class);
    }
}
