<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ringkasan pesanan marketplace hasil sinkron (hanya baca di Bizbos) --
 * lihat App\Services\Marketplace\Lazada\LazadaOrderSync.
 */
class MarketplaceOrder extends Model
{
    use HasUuids;

    protected $table = 'marketplace_orders';

    /** Status pesanan Lazada => label tampilan. Status lain tampil apa adanya. */
    public const STATUS_LABELS = [
        'unpaid' => 'Belum dibayar',
        'pending' => 'Menunggu diproses',
        'topack' => 'Perlu dikemas',
        'packed' => 'Sudah dikemas',
        'toship' => 'Siap dikirim',
        'ready_to_ship' => 'Siap dikirim',
        'shipped' => 'Dalam pengiriman',
        'delivered' => 'Diterima pembeli',
        'confirmed' => 'Selesai',
        'canceled' => 'Dibatalkan',
        'returned' => 'Dikembalikan',
        'failed' => 'Gagal kirim',
        'lost_by_3pl' => 'Hilang oleh kurir',
        'damaged_by_3pl' => 'Rusak oleh kurir',
    ];

    protected $fillable = [
        'marketplace_shop_id',
        'company_id',
        'branch_office_id',
        'external_order_id',
        'order_number',
        'status',
        'buyer_name',
        'total_amount',
        'item_count',
        'payment_method',
        'ordered_at',
        'external_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'item_count' => 'integer',
            'ordered_at' => 'datetime',
            'external_updated_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(MarketplaceShop::class, 'marketplace_shop_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? str_replace('_', ' ', (string) $this->status);
    }
}
