<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A subject/course offered at one specific cabang — see the migration's
 * docblock for why this is branch-scoped rather than company-wide.
 */
class MataPelajaran extends Model
{
    protected $table = 'mata_pelajaran';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'name',
        'description',
        'durasi_menit',
        'status',
    ];

    protected $casts = [
        'durasi_menit' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function jadwalKelas()
    {
        return $this->hasMany(JadwalKelas::class);
    }
}
