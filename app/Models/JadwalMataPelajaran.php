<?php

namespace App\Models;

use App\Helpers\JadwalImageUploader;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * "Mata Pelajaran / Bidang" — katalog bidang pendidikan yang dipakai
 * fitur Jadwal (Piano, Bahasa Inggris, Vokal, dst.), generik lintas
 * bidang. Lihat App\Http\Controllers\Jadwal\JadwalMataPelajaranController
 * untuk CRUD-nya dan the migration's docblock for how this relates to
 * the "Jadwal" module that existed here before (removed 2026-08-21).
 */
class JadwalMataPelajaran extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_mata_pelajaran';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'name',
        'description',
        'image',
        'status',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function kelas()
    {
        return $this->hasMany(JadwalKelas::class, 'jadwal_mata_pelajaran_id');
    }

    /**
     * Public URL of `image`, or null when none is set — see
     * App\Helpers\JadwalImageUploader::url().
     */
    public function getImageUrlAttribute(): ?string
    {
        return JadwalImageUploader::url($this->image);
    }
}
