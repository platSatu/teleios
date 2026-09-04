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
 *
 * Update 7 September 2026 (lihat docblock migration
 * add_reminder_rule_to_jadwal_kelas_reminder_logs_table.php) -- unique
 * key SEKARANG komposit (jadwal_kelas_id, jadwal_reminder_rule_id),
 * BUKAN jadwal_kelas_id saja lagi, supaya satu Jadwal Kelas bisa punya
 * SATU baris log PER App\Models\JadwalReminderRule (satu company bisa
 * punya banyak rule sekaligus, mis. "1 hari sebelumnya" DAN "6 jam
 * sebelumnya"). `jadwal_reminder_rule_id` NULLABLE -- baris log lama
 * (dari sebelum migration ini, atau dari rule yang sudah dihapus admin)
 * tetap ada dengan rule_id NULL, lihat docblock migration untuk
 * konsekuensinya.
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
        'jadwal_reminder_rule_id',
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

    /** Update 7 September 2026 -- lihat docblock class di atas. Bisa null (baris log historis sebelum multi-rule, atau rule-nya sudah dihapus admin, lihat nullOnDelete() di migration). */
    public function reminderRule(): BelongsTo
    {
        return $this->belongsTo(JadwalReminderRule::class, 'jadwal_reminder_rule_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
