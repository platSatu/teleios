<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One "Chat > Third Party > Google Form" integration — see the migration
 * (2026_08_06_150000_create_wa_form_integrations_table.php) for the full
 * rationale and App\Http\Controllers\Api\GoogleFormWebhookController for
 * how a submission actually gets turned into an outbound WhatsApp reply.
 *
 * `webhook_token` is generated once here (same "always system-generated"
 * convention as App\Models\WaApiKey's token/secret_key) — never editable
 * through the form, only rotatable via regenerateWebhookToken().
 */
class WaFormIntegration extends Model
{
    protected $table = 'wa_form_integrations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'type',
        'name',
        'device_id',
        'wa_message_template_id',
        'target_number_field',
        'webhook_token',
        'status',
        'created_by',
        'last_triggered_at',
        'trigger_count',
    ];

    protected $casts = [
        'last_triggered_at' => 'datetime',
        'trigger_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $integration) {
            if (empty($integration->id)) {
                $integration->id = (string) Str::uuid();
            }

            if (empty($integration->webhook_token)) {
                $integration->webhook_token = self::generateUniqueWebhookToken();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function waMessageTemplate(): BelongsTo
    {
        return $this->belongsTo(WaMessageTemplate::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(WaFormSubmission::class)->latest();
    }

    /**
     * `gf_` prefix makes a leaked token instantly recognizable, same idea
     * as WaApiKey's `wa_` token prefix. Doesn't need to be secret-secret
     * (worst case someone spams a WA send through one device using
     * whatever template is configured) but IS the only thing standing
     * between "anyone on the internet" and "posts trigger a WhatsApp
     * send", so it's still long and re-rolled until unique.
     */
    public static function generateUniqueWebhookToken(): string
    {
        do {
            $token = 'gf_'.Str::random(40);
        } while (self::where('webhook_token', $token)->exists());

        return $token;
    }

    public function regenerateWebhookToken(): void
    {
        $this->forceFill(['webhook_token' => self::generateUniqueWebhookToken()])->save();
    }
}
