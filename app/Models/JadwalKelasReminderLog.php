<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak klaim/kirim pengingat WA untuk satu App\Models\JadwalKelas --
 * lihat migration create_jadwal_kelas_reminder_logs_table.php's
 * docblock untuk kenapa jadwal_kelas_id unique (bukan komposit seperti
 * App\Models\WaMessageScheduleLog).
 */
class JadwalKelasReminderLog extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_kelas_reminder_logs';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'jadwal_kelas_id',
        'company_id',
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

    public function jadwalKelas(): BelongsTo
    {
        return $this->belongsTo(JadwalKelas::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
