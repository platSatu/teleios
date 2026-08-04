<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminWalletAction extends Model
{
    protected $table = 'admin_wallet_actions';


    protected $keyType = 'string';

    public $incrementing = false;



    protected $fillable = [

        'wallet_id',

        'admin_id',

        'action',

        'amount',

        'direction',

        'reason',

        'status',

        'approved_by',

        'approved_at',

        'old_value',

        'new_value',

    ];



    protected $casts = [

        'amount' => 'decimal:2',

        'old_value' => 'array',

        'new_value' => 'array',

        'approved_at' => 'datetime',

    ];



    protected static function boot()
    {
        parent::boot();



        static::creating(function ($action) {


            if(empty($action->id)){

                $action->id =
                    (string) Str::uuid();

            }


        });



        /**
         * Admin action tidak boleh dihapus
         * untuk menjaga audit trail
         */
        static::deleting(function(){

            throw new \Exception(
                'Admin wallet action cannot be deleted.'
            );

        });

    }


    /**
     * Wallet yang diproses
     */
    public function wallet()
    {
        return $this->belongsTo(
            Wallet::class,
            'wallet_id'
        );
    }


    /**
     * Admin yang membuat request
     */
    public function admin()
    {
        return $this->belongsTo(
            User::class,
            'admin_id'
        );
    }


    /**
     * Admin yang melakukan approval
     */
    public function approver()
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

}