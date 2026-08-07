<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One dated occurrence of a JadwalKelas — whole-class level status
 * (did this date happen, did the WHOLE class move) plus the guru's own
 * reminder/confirm tracking. Individual student attendance for this
 * same date lives in JadwalKelasSesiMurid, since one student's status
 * can diverge from the class's own status on the same date (pindah
 * hari, absen, tidak ada kabar — while the class itself still runs).
 */
class JadwalKelasSesi extends Model
{
    protected $table = 'jadwal_kelas_sesi';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'jadwal_kelas_id',
        'tanggal',
        'status',
        'tanggal_pindah',
        'jam_mulai_override',
        'jam_selesai_override',
        'catatan',
        'guru_reminder_sent_at',
        'guru_confirmed_at',
        'guru_status',
        'guru_pengganti_user_id',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'tanggal_pindah' => 'date',
        'guru_reminder_sent_at' => 'datetime',
        'guru_confirmed_at' => 'datetime',
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

    public function jadwalKelas()
    {
        return $this->belongsTo(JadwalKelas::class);
    }

    public function muridSesi()
    {
        return $this->hasMany(JadwalKelasSesiMurid::class);
    }

    public function guruPengganti()
    {
        return $this->belongsTo(User::class, 'guru_pengganti_user_id');
    }

    /**
     * Effective jam_mulai/jam_selesai for THIS date — the one-off
     * override if a guru moved just this date's start/end time
     * (jam_mulai_override/jam_selesai_override), otherwise falls back to
     * the recurring JadwalKelas pattern. Always load 'jadwalKelas' first
     * if you need the fallback to actually resolve (loadMissing here
     * would silently N+1 per row when used in a list).
     */
    public function effectiveJamMulai(): ?string
    {
        return $this->jam_mulai_override ?: $this->jadwalKelas?->jam_mulai;
    }

    public function effectiveJamSelesai(): ?string
    {
        return $this->jam_selesai_override ?: $this->jadwalKelas?->jam_selesai;
    }
}
