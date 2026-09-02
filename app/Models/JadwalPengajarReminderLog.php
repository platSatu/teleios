<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak klaim/kirim REKAP WA harian ke pengajar -- satu baris per
 * (pengajar_id, reminder_date), lihat migration
 * create_jadwal_pengajar_reminder_logs_table.php's docblock untuk
 * kenapa ini terpisah dari App\Models\JadwalKelasReminderLog.
 */
class JadwalPengajarReminderLog extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_pengajar_reminder_logs';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'pengajar_id',
        'reminder_date',
        'status',
        'message_id',
        'attempts',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'reminder_date' => 'date',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function pengajar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengajar_id');
    }
}
