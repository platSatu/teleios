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

    protected $casts = [
        'is_pengajar' => 'boolean',
    ];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        // Nullable, like branch_office_id — locks this role to one
        // Division within that branch. See migration
        // 2026_08_11_100000_add_branch_office_unit_id_to_company_roles_table's
        // docblock for why roles are locked to a division rather than
        // reusable across several.
        'branch_office_unit_id',
        'name',
        'description',
        'status',
        // Whether members holding this role should appear in the Jadwal
        // module's "Pengajar" dropdowns — see migration
        // 2026_09_14_090000_add_is_pengajar_to_company_roles_table and
        // ResolvesCompanyContext::companyPengajarMembers().
        'is_pengajar',
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

    /**
     * The one Division this role belongs to — null for legacy
     * company-wide/branch-wide roles created before this column existed.
     */
    public function branchOfficeUnit()
    {
        return $this->belongsTo(BranchOfficeUnit::class);
    }

    /**
     * Every company_role_menus row for this role — see
     * App\Models\CompanyRoleMenu. A role's allowed CategoryApplication(s)
     * are derived from this (distinct category_application_id across
     * these rows), not a separate pivot — see 2026_08_11_100100's
     * docblock for why a dedicated pivot was dropped in favor of this.
     */
    public function roleMenus()
    {
        return $this->hasMany(CompanyRoleMenu::class);
    }
}
