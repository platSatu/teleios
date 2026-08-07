<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One murid-initiated custom makeup-time proposal awaiting the guru's
 * WA approval — see the migration's docblock for the full lifecycle and
 * why this is a separate table from jadwal_kelas_sesi_murid's own
 * pindah_ke_sesi_id (that one only ever points at an ALREADY EXISTING
 * class's slot; this one is for a brand new ad-hoc date/time nobody
 * else has committed to yet).
 */
class JadwalUsulanPerubahan extends Model
{
    protected $table = 'jadwal_usulan_perubahan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'jadwal_kelas_id',
        'jadwal_kelas_sesi_murid_id',
        'guru_user_id',
        'murid_user_id',
        'tanggal_usulan',
        'jam_mulai_usulan',
        'jam_selesai_usulan',
        'status',
        'catatan',
        'diajukan_oleh',
        'reminder_sent_at',
        'responded_at',
    ];

    protected $casts = [
        'tanggal_usulan' => 'date',
        'reminder_sent_at' => 'datetime',
        'responded_at' => 'datetime',
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

    public function jadwalKelas()
    {
        return $this->belongsTo(JadwalKelas::class);
    }

    public function sesiMurid()
    {
        return $this->belongsTo(JadwalKelasSesiMurid::class, 'jadwal_kelas_sesi_murid_id');
    }

    public function guru()
    {
        return $this->belongsTo(User::class, 'guru_user_id');
    }

    public function murid()
    {
        return $this->belongsTo(User::class, 'murid_user_id');
    }
}
