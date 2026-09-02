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

    /**
     * Mata Pelajaran / Bidang milik fitur Jadwal yang di-set ke branch
     * ini — dipakai App\Http\Controllers\Jadwal\JadwalBranchController
     * untuk kolom jumlah di index-nya.
     */
    public function jadwalMataPelajarans()
    {
        return $this->hasMany(JadwalMataPelajaran::class, 'branch_office_id');
    }

    /**
     * Form Category milik fitur Form yang di-set ke branch ini --
     * dipakai App\Http\Controllers\Form\FormBranchController untuk
     * kolom jumlah di index-nya, pola sama persis dengan
     * jadwalMataPelajarans() di atas.
     */
    public function formCategories()
    {
        return $this->hasMany(FormCategory::class, 'branch_office_id');
    }

    public function units()
    {
        return $this->hasMany(BranchOfficeUnit::class);
    }

    /**
     * Jam Operasional (Jadwal v2, CLAUDE.md item #15) -- satu baris per
     * branch, lihat App\Models\JadwalBranchSetting.
     */
    public function jadwalBranchSetting()
    {
        return $this->hasOne(JadwalBranchSetting::class, 'branch_office_id');
    }

    /** Daftar Ruangan (Jadwal v2) milik branch ini. */
    public function jadwalRuangans()
    {
        return $this->hasMany(JadwalRuangan::class, 'branch_office_id');
    }
}
