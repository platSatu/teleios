<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris jadwal kelas kursus — 1 pengajar + 1 murid + rentang
 * waktu, terhubung opsional ke satu App\Models\JadwalMataPelajaran.
 * Lihat App\Http\Controllers\Jadwal\JadwalKelasController untuk CRUD-nya
 * and the migration's docblock for why pengajar/student both point at
 * `users` and why this is 1-ke-1 instead of a roster.
 */
class JadwalKelas extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_kelas';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'jadwal_mata_pelajaran_id',
        'pengajar_id',
        'student_id',
        'start_time',
        'end_time',
        'status',
        'description',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

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
        return $this->belongsTo(JadwalMataPelajaran::class, 'jadwal_mata_pelajaran_id');
    }

    public function pengajar()
    {
        return $this->belongsTo(User::class, 'pengajar_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
