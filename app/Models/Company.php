<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A user's company profile — the "Company" tab on the consolidated
 * dashboard/user/profile page (see User\Profile\ProfileController).
 * `slug` is deliberately NOT generated here: the controller derives it
 * from `name` at create/update time, per the original spec. `company_id`
 * IS generated here, since it's a pure system value the user never
 * supplies or edits.
 */
class Company extends Model
{
    protected $table = 'companies';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'company_id',
        'name',
        'slug',
        'description',
        'logo',
        'address',
        'phone',
        'email',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $company) {
            if (empty($company->id)) {
                $company->id = (string) Str::uuid();
            }

            if (empty($company->company_id)) {
                $company->company_id = self::generateUniqueCompanyId();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function roles()
    {
        return $this->hasMany(CompanyRole::class);
    }

    public function members()
    {
        return $this->hasMany(CompanyToUser::class);
    }

    public function roleMenus()
    {
        return $this->hasMany(CompanyRoleMenu::class);
    }

    public function branchOffices()
    {
        return $this->hasMany(BranchOffice::class);
    }

    /**
     * 3 random uppercase letters + 3 random digits (e.g. "ABC123"),
     * re-rolled in a loop until it's actually unique in the table —
     * same "guarantee, don't just hope" pattern as
     * App\Models\ReferralCode::generateUniqueCode().
     */
    public static function generateUniqueCompanyId(): string
    {
        do {
            $letters = collect(range('A', 'Z'))->random(3)->implode('');
            $digits  = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
            $code    = $letters . $digits;
        } while (self::where('company_id', $code)->exists());

        return $code;
    }
}
