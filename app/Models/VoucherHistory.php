<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class VoucherHistory extends Model
{
    use HasFactory, HasUuids;


    protected $table = 'voucher_histories';


    protected $keyType = 'string';


    public $incrementing = false;


    protected $fillable = [

        'voucher_id',
        'user_id',
        'action_by',
        'action',
        'old_data',
        'new_data',
        'description',

    ];


    protected $casts = [

        'old_data' => 'array',
        'new_data' => 'array',

    ];



    public function voucher()
    {
        return $this->belongsTo(
            Voucher::class,
            'voucher_id'
        );
    }


    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }


    public function actionBy()
    {
        return $this->belongsTo(
            User::class,
            'action_by'
        );
    }
}