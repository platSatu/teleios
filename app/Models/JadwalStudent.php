<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu murid — letaknya di antara App\Models\JadwalMataPelajaran/
 * pengajar dan App\Models\JadwalKelas dalam alur drill-down Jadwal
 * (Branch -> Mata Pelajaran / Bidang -> Pengajar -> Student -> Jadwal).
 * `name` bebas teks — murid TIDAK harus punya akun `users` sendiri,
 * beda dari `pengajar_id` yang tetap FK ke `users` (lihat App\Http\
 * Controllers\Jadwal\JadwalStudentController & the migration's
 * docblock).
 *
 * `parent_phone_number`/`student_phone_number` opsional, independen
 * satu sama lain — disiapkan untuk fitur pengingat WA & permintaan
 * reschedule Jadwal (belum dipakai fitur mana pun saat ini).
 */
class JadwalStudent extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_student';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'jadwal_mata_pelajaran_id',
        'pengajar_id',
        'name',
        'parent_phone_number',
        'student_phone_number',
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

    public function mataPelajaran()
    {
        return $this->belongsTo(JadwalMataPelajaran::class, 'jadwal_mata_pelajaran_id');
    }

    public function pengajar()
    {
        return $this->belongsTo(User::class, 'pengajar_id');
    }

    public function jadwalKelas()
    {
        return $this->hasMany(JadwalKelas::class, 'student_id');
    }
}
