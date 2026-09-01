<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu permintaan ubah jadwal dari orang tua/murid lewat chatbot flow WA
 * -- lihat migration create_jadwal_kelas_reschedule_requests_table.php's
 * docblock. Diproses manual oleh staff di App\Http\Controllers\Jadwal\
 * JadwalRescheduleRequestController -- baris ini sendiri TIDAK PERNAH
 * mengubah App\Models\JadwalKelas secara otomatis.
 *
 * `requested_new_start_time`/`requested_new_end_time` (nullable) diisi
 * OTOMATIS kalau flow-nya pakai step pilihan jam kosong (lihat migration
 * add_requested_schedule_to_..._table.php) -- sekadar apa yang DIMINTA
 * murid, bukan keputusan; staff tetap yang menentukan jadwal final
 * lewat form approve().
 */
class JadwalKelasRescheduleRequest extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_kelas_reschedule_requests';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    protected $fillable = [
        'company_id',
        'jadwal_student_id',
        'jadwal_kelas_id',
        'device_id',
        'chat_jid',
        'requester_phone',
        'detail_request',
        'requested_new_start_time',
        'requested_new_end_time',
        'status',
        'staff_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'requested_new_start_time' => 'datetime',
        'requested_new_end_time' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function jadwalStudent(): BelongsTo
    {
        return $this->belongsTo(JadwalStudent::class, 'jadwal_student_id');
    }

    public function jadwalKelas(): BelongsTo
    {
        return $this->belongsTo(JadwalKelas::class, 'jadwal_kelas_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
