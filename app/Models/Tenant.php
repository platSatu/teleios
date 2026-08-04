<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tenant extends Model
{
    protected $table = 'tenants';


    protected $keyType = 'string';

    public $incrementing = false;



    protected $fillable = [

        'owner_id',

        'name',

        'slug',

        'description',

        'phone',

        'email',

        'address',

        'status',

    ];



    protected static function boot()
    {
        parent::boot();


        static::creating(function ($tenant) {

            if (empty($tenant->id)) {

                $tenant->id = (string) Str::uuid();

            }


            /**
             * Generate slug otomatis
             */
            if (empty($tenant->slug)) {

                $tenant->slug =
                    Str::slug($tenant->name)
                    . '-'
                    . Str::random(5);

            }

        });

    }



    /**
     * Owner utama tenant
     *
     * User pertama yang membuat tenant
     */
    public function owner()
    {
        return $this->belongsTo(
            User::class,
            'owner_id'
        );
    }



    /**
     * Semua user yang tergabung
     */
    public function users()
    {
        return $this->belongsToMany(
            User::class,
            'tenant_users',
            'tenant_id',
            'user_id'
        )
        ->withPivot([
            'role_id',
            'status'
        ])
        ->withTimestamps();
    }



    /**
     * Relasi langsung ke pivot
     */
    public function tenantUsers()
    {
        return $this->hasMany(
            TenantUser::class,
            'tenant_id'
        );
    }



    /**
     * Subscription SaaS
     */
    public function subscriptions()
    {
        return $this->hasMany(
            Subscription::class,
            'tenant_id'
        );
    }

}