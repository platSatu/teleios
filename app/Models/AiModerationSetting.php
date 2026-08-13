<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Singleton settings row — the superadmin-owned AI content moderator
 * used by App\Services\Moderation\TemplateModerationService to check
 * (and, when possible, auto-correct) every Kategori Template
 * (App\Models\WaCategoryTemplate) and WA Template
 * (App\Models\WaMessageTemplate) a company creates or edits, replacing
 * the old manual superadmin approve/reject queue. See the migration's
 * docblock for the full design rationale.
 *
 * Always accessed through current() rather than a direct query — that's
 * what guarantees exactly one row ever exists, created lazily
 * (defaulted to inactive/unconfigured) the first time anything asks for
 * it instead of requiring a seeder to run first.
 */
class AiModerationSetting extends Model
{
    protected $table = 'ai_moderation_settings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'wa_ai_bot_provider_id',
        'wa_ai_bot_model_id',
        'api_key',
        'block_pornography',
        'block_gambling',
        'block_drugs',
        'block_negative_language',
        'blocked_keywords',
        'custom_instructions',
        'status',
        'updated_by',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'block_pornography' => 'boolean',
        'block_gambling' => 'boolean',
        'block_drugs' => 'boolean',
        'block_negative_language' => 'boolean',
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

    public function model()
    {
        return $this->belongsTo(WaAiBotModel::class, 'wa_ai_bot_model_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The one row this table ever needs — created with safe defaults
     * (inactive, unconfigured) on first access rather than requiring a
     * seeder. Every caller (the settings form, TemplateModerationService)
     * goes through this instead of querying the table directly.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::create(['status' => 'inactive']);
    }

    /**
     * Whether moderation can actually run right now — active AND every
     * piece it needs (provider, model, key) is present. Anything short
     * of this and TemplateModerationService returns an "unavailable"
     * result rather than attempting a call that would just fail.
     */
    public function isUsable(): bool
    {
        return $this->status === 'active'
            && ! empty($this->wa_ai_bot_provider_id)
            && ! empty($this->wa_ai_bot_model_id)
            && ! empty($this->api_key);
    }
}
