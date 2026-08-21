<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One murid's attendance/reschedule record for one dated
 * JadwalKelasSesi occurrence — the row the reminder + auto-confirm flow
 * revolves around. App\Console\Commands (reminder job) stamps
 * reminder_sent_at; App\Http\Controllers\Api\WaIncomingMessageWebhookController
 * matches an inbound WA reply back to whichever row is still awaiting
 * confirmation for that sender's phone number and updates `status`/
 * `confirmed_at` directly — no manual step in between (that manual gap
 * is exactly what this feature replaces).
 */
class JadwalKelasSesiMurid extends Model
{
    protected $table = 'jadwal_kelas_sesi_murid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'jadwal_kelas_sesi_id',
        'jadwal_kelas_murid_id',
        'status',
        'tanggal_pindah',
        'pindah_ke_sesi_id',
        'catatan',
        'reminder_sent_at',
        'confirmed_at',
        'confirmation_channel',
    ];

    protected $casts = [
        'tanggal_pindah' => 'date',
        'reminder_sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
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

    public function sesi()
    {
        return $this->belongsTo(JadwalKelasSesi::class, 'jadwal_kelas_sesi_id');
    }

    public function jadwalKelasMurid()
    {
        return $this->belongsTo(JadwalKelasMurid::class);
    }

    /**
     * The alternate JadwalKelasSesi (possibly under a different
     * JadwalKelas entirely) this murid was offered/moved into — see
     * migration 2026_08_07_130100 and App\Services\Jadwal\
     * JadwalAvailabilityService::findAlternativeSlotsForMurid().
     */
    public function pindahKeSesi()
    {
        return $this->belongsTo(JadwalKelasSesi::class, 'pindah_ke_sesi_id');
    }

    /**
     * Convenience accessor to the actual murid User through the
     * enrollment row, since this table itself only has
     * jadwal_kelas_murid_id.
     */
    public function murid()
    {
        return $this->hasOneThrough(
            User::class,
            JadwalKelasMurid::class,
            'id', // jadwal_kelas_murid.id
            'id', // users.id
            'jadwal_kelas_murid_id', // this table's FK to jadwal_kelas_murid
            'murid_user_id' // jadwal_kelas_murid's FK to users
        );
    }
}
