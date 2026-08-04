<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Role extends Model
{
    protected $table = 'roles';


    protected $keyType = 'string';

    public $incrementing = false;



    protected $fillable = [

        'name',

        'guard_name',

        'description',

        'status',

    ];



    protected static function boot()
    {
        parent::boot();


        static::creating(function ($role) {

            if (empty($role->id)) {

                $role->id = (string) Str::uuid();

            }

        });

    }



    /**
     * Relasi ke tenant users
     */
    public function tenantUsers()
    {
        return $this->hasMany(
            TenantUser::class,
            'role_id'
        );
    }



    /**
     * User yang memiliki role ini melalui tenant
     */
    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'tenant_users',
            'role_id',
            'user_id'
        )
        ->withPivot([
            'tenant_id',
            'status'
        ])
        ->withTimestamps();
    }

}