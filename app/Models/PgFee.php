<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/** Tarif per metode bayar (kode Duitku), diatur superadmin; dipotong dari dana masuk. */
class PgFee extends Model
{
    use HasUuidPrimaryKey;

    public const TYPES = ['va' => 'Virtual Account', 'qris' => 'QRIS', 'ewallet' => 'E-Wallet', 'other' => 'Lainnya'];

    protected $table = 'pg_fees';

    protected $fillable = ['payment_method', 'name', 'type', 'fee_percent', 'fee_flat', 'is_active', 'sort_order'];

    protected $casts = [
        'fee_percent' => 'decimal:3',
        'fee_flat' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Fee dibulatkan ke atas ke rupiah penuh. */
    public function feeFor(float $amount): float
    {
        return (float) ceil($amount * (float) $this->fee_percent / 100 + (float) $this->fee_flat);
    }

    public function label(): string
    {
        $parts = array_filter([
            (float) $this->fee_percent ? rtrim(rtrim(number_format((float) $this->fee_percent, 3, ',', '.'), '0'), ',').'%' : null,
            (float) $this->fee_flat ? 'Rp '.number_format((float) $this->fee_flat, 0, ',', '.') : null,
        ]);

        return $parts ? implode(' + ', $parts) : 'Gratis';
    }
}
