<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Toko marketplace (Lazada, nanti Shopee/TikTok) yang dihubungkan pelanggan
 * ke salah satu branch-nya -- lihat migration create_marketplace_tables dan
 * App\Services\Marketplace\Lazada\LazadaShopConnector.
 *
 * Token OAuth terenkripsi (cast 'encrypted', pola sama dengan DuitkuSetting)
 * dan disembunyikan dari toArray()/JSON supaya tidak pernah ikut terkirim ke
 * view/log secara tidak sengaja.
 */
class MarketplaceShop extends Model
{
    use HasUuids;

    public const PROVIDER_LAZADA = 'lazada';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DISCONNECTED = 'disconnected';

    protected $table = 'marketplace_shops';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'connected_by_user_id',
        'provider',
        'external_shop_id',
        'name',
        'country',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
        'status',
        'orders_synced_until',
        'last_synced_at',
        'last_sync_error',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
            'orders_synced_until' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /** Label status untuk tampilan. */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Terhubung',
            self::STATUS_EXPIRED => 'Perlu dihubungkan ulang',
            self::STATUS_DISCONNECTED => 'Diputus',
            default => (string) $this->status,
        };
    }
}
