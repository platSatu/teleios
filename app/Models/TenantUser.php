<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TenantUser extends Model
{
    protected $table = 'tenant_users';


    protected $keyType = 'string';

    public $incrementing = false;



    protected $fillable = [

        'tenant_id',

        'user_id',

        'role_id',

        'status',

        'joined_at',

    ];



    protected $casts = [

        'joined_at' => 'datetime',

    ];



    protected static function boot()
    {
        parent::boot();


        static::creating(function ($tenantUser) {

            if(empty($tenantUser->id)) {

                $tenantUser->id = (string) Str::uuid();

            }

        });

    }



    /**
     * Tenant tempat user bergabung
     */
    public function tenant()
    {
        return $this->belongsTo(
            Tenant::class,
            'tenant_id'
        );
    }



    /**
     * User anggota tenant
     */
    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }



    /**
     * Role user dalam tenant tersebut
     */
    public function role()
    {
        return $this->belongsTo(
            Role::class,
            'role_id'
        );
    }

}