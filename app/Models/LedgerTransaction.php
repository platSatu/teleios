<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LedgerTransaction extends Model
{
    protected $table = 'ledger_transactions';


    protected $keyType = 'string';

    public $incrementing = false;


    protected $fillable = [
        'transaction_number',
        'transaction_type',
        'reference_type',
        'reference_id',
        'status',
        'description',
        'created_by',
    ];


    protected static function boot()
    {
        parent::boot();


        static::creating(function ($transaction) {

            if (empty($transaction->id)) {
                $transaction->id = (string) Str::uuid();
            }


            /**
             * Generate transaction number
             *
             * Contoh:
             * TRX-202607170001
             */
            if (empty($transaction->transaction_number)) {

                $transaction->transaction_number =
                    'TRX-' .
                    now()->format('YmdHis') .
                    strtoupper(Str::random(6));

            }

        });
    }



    /**
     * Transaction memiliki banyak ledger entries
     */
    public function entries()
    {
        return $this->hasMany(
            LedgerEntry::class,
            'transaction_id'
        );
    }



    /**
     * Siapa yang membuat transaksi ini (Auth::id() saat itu — bisa user
     * biasa lewat deposit, atau superadmin lewat penyesuaian saldo).
     */
    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}