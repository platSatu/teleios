<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Deposit extends Model
{
    protected $table = 'deposits';


    protected $keyType = 'string';

    public $incrementing = false;



    protected $fillable = [
        'user_id',
        'reference_number',
        'idempotency_key',
        'amount',
        'currency',
        'payment_method',
        'payment_provider',
        'provider_transaction_id',
        'status',
        'paid_at',
        'expires_at',
        'reminder_sent_at',
        'failure_reason',
        'metadata',
    ];



    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'metadata' => 'array',
    ];



    protected static function boot()
    {
        parent::boot();


        static::creating(function ($deposit) {

            if (empty($deposit->id)) {

                $deposit->id = (string) Str::uuid();

            }


            /**
             * Generate nomor deposit
             */
            if (empty($deposit->reference_number)) {

                $deposit->reference_number =
                    'DEP-' .
                    now()->format('YmdHis') .
                    strtoupper(Str::random(6));

            }

        });


        /**
         * Deposit tidak boleh dihapus
         * jika sudah sukses
         */
        static::deleting(function ($deposit) {

            if ($deposit->status === 'SUCCESS') {

                throw new \Exception(
                    'Successful deposit cannot be deleted.'
                );

            }

        });

    }



    /**
     * Deposit belongs to user
     */
    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }



    /**
     * Deposit menghasilkan ledger transaction
     */
    public function ledgerTransaction()
    {
        return $this->morphOne(
            LedgerTransaction::class,
            'reference'
        );
    }
}