<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Enrollment of one murid into one JadwalKelas — the roster. Actual
 * per-date attendance/reschedule tracking lives one level down in
 * JadwalKelasSesiMurid.
 */
class JadwalKelasMurid extends Model
{
    protected $table = 'jadwal_kelas_murid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'jadwal_kelas_id',
        'murid_user_id',
        'status',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }

            if (empty($model->joined_at)) {
                $model->joined_at = now();
            }
        });
    }

    public function jadwalKelas()
    {
        return $this->belongsTo(JadwalKelas::class);
    }

    public function murid()
    {
        return $this->belongsTo(User::class, 'murid_user_id');
    }

    public function sesi()
    {
        return $this->hasMany(JadwalKelasSesiMurid::class);
    }
}
