<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TransactionStatusHistory extends Model
{
    protected $table = 'transaction_status_histories';


    protected $keyType = 'string';

    public $incrementing = false;


    protected $fillable = [
        'entity_type',
        'entity_id',
        'old_status',
        'new_status',
        'changed_by',
    ];


    protected static function boot()
    {
        parent::boot();


        static::creating(function ($history) {

            if (empty($history->id)) {

                $history->id = (string) Str::uuid();

            }

        });

    }

    public function changer()
    {
        return $this->belongsTo(
            User::class,
            'changed_by'
        );
    }
}