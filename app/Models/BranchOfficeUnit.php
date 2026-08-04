<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A unit/divisi belonging to a BranchOffice (see App\Models\BranchOffice).
 */
class BranchOfficeUnit extends Model
{
    protected $table = 'branch_office_units';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'branch_office_id',
        'name',
        'description',
        'status',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $unit) {
            if (empty($unit->id)) {
                $unit->id = (string) Str::uuid();
            }
        });
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }
}
