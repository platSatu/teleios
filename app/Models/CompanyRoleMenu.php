<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Which Application Menu entries a given CompanyRole (see
 * App\Models\CompanyRole) can see, grouped by Category Application. See
 * User\Profile\CompanyRoleMenuController (the company owner's
 * "Applications" tab — a role x menu checkbox matrix) and Superadmin\
 * CompanyRoleMenuController (cross-company view for troubleshooting).
 *
 * `company_role_id` is nullable at the column level for historical rows
 * that predate it (see migration
 * 2026_08_03_170000_add_company_role_id_to_company_role_menus_table),
 * but every row created going forward always has one — enforced by
 * CompanyRoleMenuController's validator, not here.
 */
class CompanyRoleMenu extends Model
{
    protected $table = 'company_role_menus';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'company_role_id',
        'category_application_id',
        'application_menu_id',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $roleMenu) {
            if (empty($roleMenu->id)) {
                $roleMenu->id = (string) Str::uuid();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function companyRole()
    {
        return $this->belongsTo(CompanyRole::class);
    }

    public function categoryApplication()
    {
        return $this->belongsTo(CategoryApplication::class);
    }

    public function applicationMenu()
    {
        return $this->belongsTo(ApplicationMenu::class);
    }
}
