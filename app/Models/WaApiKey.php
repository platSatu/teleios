<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One API key/secret pair per connected WhatsApp device, letting a third
 * party send messages through that device without ever logging into
 * this dashboard — see App\Http\Middleware\VerifyWaApiKey (how a request
 * authenticates against this table) and App\Http\Controllers\Api\
 * WaApiSendMessageController (the one thing it's currently allowed to do:
 * send a message, e.g. as a notification channel).
 *
 * Generated/regenerated from the Device page (dashboard/chat/connect-
 * device — see App\Http\Controllers\Chat\WaApiKeyController), never
 * created through a plain form: `token`/`secret_key` are always machine-
 * generated (generateUniqueToken()/generateUniqueSecret() below), same
 * "always system-generated, re-rolled until unique" pattern as
 * App\Models\ReferralCode::generateUniqueCode() and
 * App\Models\Company::generateUniqueCompanyId().
 *
 * `device_id` is a plain string, no FK — this app has no local `devices`
 * table (WhatsApp devices live entirely in the Go backend), same
 * convention as device_id on WaMessageAutoReply/WaMessageSchedule/etc.
 */
class WaApiKey extends Model
{
    protected $table = 'wa_api_keys';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'device_id',
        'device_label',
        'api_host',
        'token',
        'secret_key',
        'status',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $apiKey) {
            if (empty($apiKey->id)) {
                $apiKey->id = (string) Str::uuid();
            }

            if (empty($apiKey->token)) {
                $apiKey->token = self::generateUniqueToken();
            }

            if (empty($apiKey->secret_key)) {
                $apiKey->secret_key = self::generateUniqueSecret();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * `wa_` prefix makes a leaked token instantly recognizable as "one of
     * ours" in a log line or a support ticket, same idea as Stripe's
     * `sk_live_...` style prefixes. 40 random chars after it — re-rolled
     * until unique, since it doubles as the lookup key in
     * VerifyWaApiKey (has to be globally unique, not just per-company).
     */
    public static function generateUniqueToken(): string
    {
        do {
            $token = 'wa_'.Str::random(40);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    /**
     * Secret doesn't need to be globally unique (it's always checked
     * together with the token, never looked up by itself), but re-rolling
     * until unique anyway costs nothing and rules out even a
     * theoretical collision.
     */
    public static function generateUniqueSecret(): string
    {
        do {
            $secret = Str::random(48);
        } while (self::where('secret_key', $secret)->exists());

        return $secret;
    }

    /**
     * Used by App\Http\Controllers\Chat\WaApiKeyController's "Regenerate
     * Token" button — old token stops working the instant this saves,
     * same "no grace period" behaviour as ReferralCodeController::regenerate().
     */
    public function regenerateToken(): void
    {
        $this->forceFill(['token' => self::generateUniqueToken()])->save();
    }

    public function regenerateSecret(): void
    {
        $this->forceFill(['secret_key' => self::generateUniqueSecret()])->save();
    }
}
