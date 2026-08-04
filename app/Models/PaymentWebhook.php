<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentWebhook extends Model
{

    protected $table = 'payment_webhooks';


    protected $keyType = 'string';

    public $incrementing = false;



    protected $fillable = [

        'provider',

        'event_type',

        'signature',

        'payload',

        'processed',

        'processed_at',

        'processing_error',

        'reference_type',

        'reference_id',

    ];



    protected $casts = [

        'payload' => 'array',

        'processed' => 'boolean',

        'processed_at' => 'datetime',

    ];



    protected static function boot()
    {
        parent::boot();


        static::creating(function ($webhook) {

            if(empty($webhook->id)) {

                $webhook->id =
                    (string) Str::uuid();

            }

        });



        /**
         * Webhook history immutable
         */
        static::deleting(function () {

            throw new \Exception(
                'Payment webhook cannot be deleted.'
            );

        });

    }

}