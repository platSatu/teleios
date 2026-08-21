<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A recurring weekly class "template" — one hari + jam_mulai/
 * jam_selesai pattern belonging to one cabang. See the migration's
 * docblock for why guru assignment isn't branch-locked (one teacher can
 * have JadwalKelas rows at several different branches) and why
 * device_id has no foreign key.
 */
class JadwalKelas extends Model
{
    protected $table = 'jadwal_kelas';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'mata_pelajaran_id',
        'guru_user_id',
        'device_id',
        'name',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'kapasitas',
        'status',
    ];

    protected $casts = [
        'kapasitas' => 'integer',
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

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_user_id');
    }

    public function murid()
    {
        return $this->hasMany(JadwalKelasMurid::class);
    }

    public function sesi()
    {
        return $this->hasMany(JadwalKelasSesi::class);
    }
}
