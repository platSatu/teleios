<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class LedgerEntry extends Model
{
    protected $table = 'ledger_entries';


    protected $keyType = 'string';

    public $incrementing = false;



    protected $fillable = [
        'transaction_id',
        'wallet_id',
        'user_id',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'entry_hash',
    ];



    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];



    protected static function boot()
    {
        parent::boot();


        static::creating(function ($entry) {

            if (empty($entry->id)) {
                $entry->id = (string) Str::uuid();
            }


            /**
             * Generate integrity hash
             *
             * Hash dibuat saat insert pertama.
             */
            if (empty($entry->entry_hash)) {

                $entry->entry_hash = hash(
                    'sha256',
                    implode('|', [
                        $entry->transaction_id,
                        $entry->wallet_id,
                        $entry->user_id,
                        $entry->direction,
                        $entry->amount,
                        $entry->balance_before,
                        $entry->balance_after,
                    ])
                );

            }

        });


        /**
         * Mencegah perubahan ledger
         */
        static::updating(function () {

            throw new \Exception(
                'Ledger entry is immutable and cannot be updated.'
            );

        });


        /**
         * Mencegah penghapusan ledger
         */
        static::deleting(function () {

            throw new \Exception(
                'Ledger entry is immutable and cannot be deleted.'
            );

        });

    }



    /**
     * Relasi ke transaksi utama
     */
    public function transaction()
    {
        return $this->belongsTo(
            LedgerTransaction::class,
            'transaction_id'
        );
    }



    /**
     * Relasi ke wallet
     */
    public function wallet()
    {
        return $this->belongsTo(
            Wallet::class,
            'wallet_id'
        );
    }



    /**
     * Relasi ke user
     */
    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}