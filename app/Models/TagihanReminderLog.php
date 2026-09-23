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
 * Diisi oleh App\Console\Commands\DispatchDueTagihanReminders (klaim,
 * status 'pending') lalu App\Jobs\SendTagihanReminder (kirim beneran,
 * naik ke 'sent'/'skipped'/'failed') -- 23 September 2026, menyusul
 * instruksi sebelumnya "jangan sambungkan dulu ya dengan whatsapp" kini
 * sudah dicabut oleh user (audit kesiapan launch).
 */
class TagihanReminderLog extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan_reminder_log';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * "Seharusnya dikirim, tapi sengaja dilewati" -- device belum
     * terhubung, pelanggan sudah lunas/dibatalkan sebelum sempat
     * terkirim, nomor HP tidak valid, dst. BEDA dari 'failed' (yang
     * berarti benar-benar dicoba kirim dan gagal) -- sama pembedaannya
     * dengan App\Models\JadwalKelasReminderLog::STATUS_SKIPPED.
     */
    public const STATUS_SKIPPED = 'skipped';

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
