<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Satu pembayaran dari website pemakai. `token` = akses halaman/popup
 * checkout (bukan id), `order_number` (prefix "PG-") = merchantOrderId ke
 * Duitku. Status `paid` HANYA diisi PgPaymentService::markPaid() dari
 * callback Duitku yang sudah diverifikasi.
 */
class PgInvoice extends Model
{
    use HasUuidPrimaryKey;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'pg_invoices';

    protected $fillable = [
        'pg_merchant_id', 'external_id', 'amount', 'description', 'customer_name', 'customer_email',
        'customer_phone', 'return_url', 'expires_at',
    ];

    protected $hidden = ['qr_string'];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $invoice) {
            $invoice->token ??= (string) Str::uuid();
            $invoice->order_number ??= 'PG-'.now()->format('ymdHis').strtoupper(Str::random(8));
            $invoice->status ??= self::STATUS_PENDING;
        });
    }

    public function isPayable(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at->isFuture();
    }

    /** Bentuk data untuk API & webhook (tanpa token/qr). */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'status' => $this->status,
            'amount' => (int) $this->amount,
            'fee' => $this->fee !== null ? (int) $this->fee : null,
            'net_amount' => $this->net_amount !== null ? (int) $this->net_amount : null,
            'payment_method' => $this->payment_method,
            'payment_name' => $this->payment_name,
            'description' => $this->description,
            'checkout_url' => route('pg.checkout.show', $this->token),
            'token' => $this->token,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(PgMerchant::class, 'pg_merchant_id');
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(PgWebhookLog::class);
    }
}
