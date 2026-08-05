<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Superadmin-managed catalog of models under one WaAiBotProvider (e.g.
 * "OpenAI (ChatGPT)" -> gpt-4o, gpt-4-turbo) — see
 * Superadmin\WaAiBotModelController. The AI Bot form's Model dropdown is
 * filtered to whichever Provider is selected, same dependent-dropdown
 * pattern as CompanyRoleMenuController's Category Application ->
 * Application Menu picker.
 */
class WaAiBotModel extends Model
{
    protected $table = 'wa_ai_bot_models';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'wa_ai_bot_provider_id',
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

    public function provider()
    {
        return $this->belongsTo(WaAiBotProvider::class, 'wa_ai_bot_provider_id');
    }
}
