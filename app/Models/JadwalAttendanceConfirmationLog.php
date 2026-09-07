<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak klaim/kirim sesi konfirmasi kehadiran WA ke pengajar -- satu
 * baris per jadwal_kelas_id, lihat migration
 * create_jadwal_attendance_confirmation_logs_table.php's docblock.
 */
class JadwalAttendanceConfirmationLog extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_attendance_confirmation_logs';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'jadwal_kelas_id',
        'pengajar_id',
        'status',
        'message_id',
        'attempts',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function jadwalKelas(): BelongsTo
    {
        return $this->belongsTo(JadwalKelas::class, 'jadwal_kelas_id');
    }

    public function pengajar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengajar_id');
    }
}
