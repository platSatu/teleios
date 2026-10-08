<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Buku saldo merchant Payment Gateway. Satu invoice paling banyak satu baris (unique). */
class PgLedger extends Model
{
    use HasUuidPrimaryKey;

    public const UPDATED_AT = null;

    protected $table = 'pg_ledger';

    protected $fillable = ['pg_merchant_id', 'pg_invoice_id', 'type', 'amount', 'balance_after', 'description'];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PgInvoice::class, 'pg_invoice_id');
    }
}
