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
        'driver',
        'status',
    ];

    /**
     * Drivers App\Services\AiBot\AiReplyGenerator knows how to call —
     * kept here (not a DB enum) so the dropdown in the superadmin form
     * and the engine's switch statement can never drift apart silently.
     */
    public const DRIVERS = [
        'gemini' => 'Google Gemini',
        'openai' => 'OpenAI (ChatGPT)',
        'anthropic' => 'Anthropic (Claude)',
        'deepseek' => 'DeepSeek',
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
