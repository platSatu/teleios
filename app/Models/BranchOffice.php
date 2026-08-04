<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A branch/cabang belonging to a Company (see App\Models\Company).
 * `slug` is derived from `name` in the controller (not here), same
 * approach as Company — see User\Settings\BranchOfficeController::
 * uniqueSlug(). `id` IS generated here since it's a pure system value.
 */
class BranchOffice extends Model
{
    protected $table = 'branch_offices';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'address',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $branchOffice) {
            if (empty($branchOffice->id)) {
                $branchOffice->id = (string) Str::uuid();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function units()
    {
        return $this->hasMany(BranchOfficeUnit::class);
    }
}
