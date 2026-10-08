<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Satu website pemakai Payment Gateway (satu per company). `secret`
 * disimpan terenkripsi (APP_KEY) dan hanya ditampilkan sekali saat dibuat.
 * Saldo bertambah HANYA dari App\Services\PaymentGateway\PgPaymentService::markPaid().
 */
class PgMerchant extends Model
{
    use HasUuidPrimaryKey;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $table = 'pg_merchants';

    protected $fillable = [
        'company_id', 'name', 'logo_url', 'api_key', 'secret', 'webhook_url',
        'allowed_domains', 'status', 'rate_limit_per_minute',
    ];

    protected $hidden = ['secret'];

    protected $casts = [
        'secret' => 'encrypted',
        'allowed_domains' => 'array',
        'rate_limit_per_minute' => 'integer',
        'balance' => 'decimal:2',
    ];

    public static function newApiKey(): string
    {
        return 'pk_'.Str::random(40);
    }

    public static function newSecret(): string
    {
        return 'sk_'.Str::random(48);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** HMAC-SHA256(secret, timestamp + "." + body) -- request API & webhook. */
    public function sign(string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$body, $this->secret);
    }

    /** Nilai CSP frame-ancestors untuk popup checkout (domain + subdomain). */
    public function frameAncestors(): string
    {
        return collect($this->allowed_domains ?? [])
            ->flatMap(fn (string $d) => ["https://{$d}", "https://*.{$d}", "http://{$d}:*", "http://*.{$d}:*"])
            ->implode(' ') ?: "'none'";
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PgInvoice::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(PgLedger::class);
    }
}
