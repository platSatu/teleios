<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Subscription extends Model
{

    protected $table = 'subscriptions';



    protected $keyType = 'string';

    public $incrementing = false;



    protected $fillable = [

        'user_id',

        'package_id',

        'amount',

        'currency',

        'start_date',

        'end_date',

        'status',

        'auto_renew',

        'payment_transaction_id',

        'metadata',

    ];



    protected $casts = [

        'amount' => 'decimal:2',

        'start_date' => 'datetime',

        'end_date' => 'datetime',

        'auto_renew' => 'boolean',

        'metadata' => 'array',

    ];



    protected static function boot()
    {
        parent::boot();


        static::creating(function ($subscription) {

            if(empty($subscription->id)) {

                $subscription->id =
                    (string) Str::uuid();

            }

        });


        /**
         * Subscription tidak boleh
         * dihapus sembarangan
         */
        static::deleting(function ($subscription) {


            if($subscription->status === 'ACTIVE') {

                throw new \Exception(
                    'Active subscription cannot be deleted.'
                );

            }

        });

    }



    /**
     * User pemilik subscription
     */
    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }



    /**
     * Relasi payment
     */
    public function paymentTransaction()
    {
        return $this->belongsTo(
            PaymentTransaction::class,
            'payment_transaction_id'
        );
    }



    /**
     * Package
     */
    public function package()
    {
        return $this->belongsTo(
            Package::class,
            'package_id'
        );
    }



    /**
     * The activation-code Voucher generated for this purchase
     * (Dashboard\PackageCheckoutController::store()).
     */
    public function voucher()
    {
        return $this->hasOne(
            Voucher::class,
            'subscription_id'
        );
    }

}