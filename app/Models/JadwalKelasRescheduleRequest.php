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
        'status',
        'staff_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
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
