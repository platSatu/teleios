<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Membership of a user in a company, under a given CompanyRole, scoped
 * to one CategoryApplication per row. The owner's own row (role
 * "Owner", category_application_id null = unrestricted) is created
 * automatically alongside the company itself — see App\Http\Controllers\
 * User\Profile\ProfileController::updateCompany(). Additional members
 * are managed from the "Setting Users" tab via User\Profile\
 * CompanyUserController, which writes one row per category a member is
 * granted (so a single user can have several rows for the same
 * company — one per CategoryApplication).
 */
class CompanyToUser extends Model
{
    protected $table = 'company_to_users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'company_id',
        'company_role_id',
        // Nullable — null means unrestricted access (the owner's own
        // auto-created row). A member granted more than one
        // CategoryApplication gets one row per category instead of a
        // list here; see User\Profile\CompanyUserController.
        'category_application_id',
        // Both nullable — a member doesn't have to be placed under a
        // branch office/unit. branch_office_unit_id only makes sense
        // alongside a branch_office_id (the unit belongs to it), which
        // User\Profile\CompanyUserController enforces at validation time.
        'branch_office_id',
        'branch_office_unit_id',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $companyToUser) {
            if (empty($companyToUser->id)) {
                $companyToUser->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function role()
    {
        return $this->belongsTo(CompanyRole::class, 'company_role_id');
    }

    public function categoryApplication()
    {
        return $this->belongsTo(CategoryApplication::class);
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function branchOfficeUnit()
    {
        return $this->belongsTo(BranchOfficeUnit::class);
    }
}
