<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AI-responder configuration for one connected device. api_configuration
 * is cast `encrypted` since it holds a tenant's own AI provider API
 * key/config — never stored (or logged) in plain text.
 */
class WaAiBot extends Model
{
    protected $table = 'wa_ai_bots';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'device_id',
        'branch_office_id',
        'ai_provider',
        'ai_model',
        'wa_ai_bot_provider_id',
        'wa_ai_bot_model_id',
        'attach_file_path',
        'attach_file_original_name',
        'api_configuration',
        'ai_behaviour_prompt',
        'active_bot_immediately',
        'custom_activation_time',
        'activation_start_at',
        'activation_end_at',
        'status',
    ];

    protected $casts = [
        'active_bot_immediately' => 'boolean',
        'custom_activation_time' => 'boolean',
        'activation_start_at' => 'datetime',
        'activation_end_at' => 'datetime',
        'api_configuration' => 'encrypted',
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function provider()
    {
        return $this->belongsTo(WaAiBotProvider::class, 'wa_ai_bot_provider_id');
    }

    public function model()
    {
        return $this->belongsTo(WaAiBotModel::class, 'wa_ai_bot_model_id');
    }
}
