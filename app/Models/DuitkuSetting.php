<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Singleton settings row — the superadmin-owned Duitku merchant
 * credentials that used to live in .env (DUITKU_MERCHANT_CODE/
 * DUITKU_API_KEY/DUITKU_SANDBOX), now editable from Superadmin >
 * Deposits > Pengaturan Duitku (App\Http\Controllers\Superadmin\
 * DuitkuSettingController) instead. See the migration's docblock for
 * the full design rationale (why two separate credential pairs, why a
 * singleton table).
 *
 * App\Services\Payment\DuitkuService::make() is the only consumer —
 * everything else about the Duitku integration (invoice creation,
 * callback signature verification, the checkout flow) is untouched by
 * this table; it only replaces WHERE those 3 values come from.
 *
 * Always accessed through current() rather than a direct query — same
 * lazy-singleton pattern as App\Models\AiModerationSetting.
 */
class DuitkuSetting extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'duitku_settings';

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_PRODUCTION = 'production';

    public const MODES = [self::MODE_SANDBOX, self::MODE_PRODUCTION];

    protected $fillable = [
        'mode',
        'sandbox_merchant_code',
        'sandbox_api_key',
        'production_merchant_code',
        'production_api_key',
        'updated_by',
    ];

    protected $casts = [
        'sandbox_api_key' => 'encrypted',
        'production_api_key' => 'encrypted',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The one row this table ever needs — created with safe defaults
     * (mode 'sandbox', no credentials yet) on first access rather than
     * requiring a seeder. Both the settings form and DuitkuService::
     * make() go through this instead of querying the table directly.
     */
    public static function current(): self
    {
        return static::query()->first() ?? static::create(['mode' => self::MODE_SANDBOX]);
    }

    public function isSandbox(): bool
    {
        return $this->mode !== self::MODE_PRODUCTION;
    }

    /** Merchant code of whichever mode is currently active. */
    public function activeMerchantCode(): ?string
    {
        return $this->isSandbox() ? $this->sandbox_merchant_code : $this->production_merchant_code;
    }

    /** API key of whichever mode is currently active. */
    public function activeApiKey(): ?string
    {
        return $this->isSandbox() ? $this->sandbox_api_key : $this->production_api_key;
    }

    /**
     * Whether DuitkuService::make() actually has everything it needs
     * for the CURRENTLY ACTIVE mode. The other (inactive) mode's
     * credentials are allowed to stay empty — a superadmin filling in
     * sandbox first, before ever touching production, is a normal
     * intermediate state, not a misconfiguration.
     */
    public function isConfigured(): bool
    {
        return filled($this->activeMerchantCode()) && filled($this->activeApiKey());
    }
}
