<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentTransaction extends Model
{

    protected $table = 'payment_transactions';


    protected $keyType = 'string';

    public $incrementing = false;



    protected $fillable = [

        'reference_type',

        'reference_id',

        'provider',

        'provider_transaction_id',

        'payment_method',

        'amount',

        'currency',

        'status',

        'request_payload',

        'response_payload',

        'callback_received_at',

        'failure_reason',

    ];



    protected $casts = [

        'amount' => 'decimal:2',

        'request_payload' => 'array',

        'response_payload' => 'array',

        'callback_received_at' => 'datetime',

    ];



    protected static function boot()
    {
        parent::boot();


        static::creating(function ($payment) {

            if(empty($payment->id)){

                $payment->id =
                    (string) Str::uuid();

            }

        });



        /**
         * Payment transaction immutable
         *
         * Tidak boleh dihapus
         */
        static::deleting(function () {

            throw new \Exception(
                'Payment transaction cannot be deleted.'
            );

        });

    }



    /**
     * Relasi polymorphic
     *
     * Bisa ke:
     * Deposit
     * Subscription
     * Purchase
     */
    public function reference()
    {
        return $this->morphTo();
    }

}