<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak "pengingat ini sudah/belum dikirim" per (App\Models\TagihanPenerima,
 * App\Models\TagihanReminderRule) -- lihat migration
 * create_tagihan_reminder_log_table.php's docblock. Struktur disamakan
 * persis dengan App\Models\JadwalKelasReminderLog.
 *
 * Belum ada job yang menulis baris ke sini sekarang -- disiapkan lebih
 * dulu supaya siap dipakai begitu bagian WhatsApp-nya digarap.
 */
class TagihanReminderLog extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan_reminder_log';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tagihan_penerima_id',
        'tagihan_reminder_rule_id',
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

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(TagihanPenerima::class, 'tagihan_penerima_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(TagihanReminderRule::class, 'tagihan_reminder_rule_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
