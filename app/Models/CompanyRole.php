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
        'branch_office_id',
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

    /**
     * Null when this role applies company-wide — see the migration's
     * docblock (2026_08_07_110000_add_branch_office_id_to_company_roles_table).
     */
    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function members()
    {
        return $this->hasMany(CompanyToUser::class);
    }
}
