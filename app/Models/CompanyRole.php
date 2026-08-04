<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A role scoped to one company (e.g. "Owner", "Admin", "Staff"). The
 * "Owner" row is auto-created for every company — see
 * App\Http\Controllers\User\Profile\ProfileController::updateCompany().
 */
class CompanyRole extends Model
{
    protected $table = 'company_roles';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $role) {
            if (empty($role->id)) {
                $role->id = (string) Str::uuid();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function members()
    {
        return $this->hasMany(CompanyToUser::class);
    }
}
