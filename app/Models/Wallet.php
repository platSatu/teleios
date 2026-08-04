<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Wallet extends Model
{
    protected $table = 'wallets';

    protected $keyType = 'string';

    public $incrementing = false;


    protected $fillable = [
        'user_id',
        'currency',
        'balance',
        'status',
    ];


    protected $casts = [
        'balance' => 'decimal:2',
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($wallet) {

            if (empty($wallet->id)) {
                $wallet->id = (string) Str::uuid();
            }

        });
    }


    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function ledgerEntries()
    {
        return $this->hasMany(
            LedgerEntry::class,
            'wallet_id'
        );
    }
}