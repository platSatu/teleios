<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu waktu pengingat (H-7/H-3/dst) milik satu App\Models\Tagihan --
 * lihat migration create_tagihan_reminder_rule_table.php's docblock.
 * Struktur & konvensi disamakan persis dengan App\Models\JadwalReminderRule.
 */
class TagihanReminderRule extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan_reminder_rule';

    public const UNIT_HOURS = 'hours';

    public const UNIT_DAYS = 'days';

    public const UNITS = [self::UNIT_HOURS, self::UNIT_DAYS];

    protected $fillable = [
        'tagihan_id',
        'remind_value',
        'remind_unit',
    ];

    protected $casts = [
        'remind_value' => 'integer',
    ];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(Tagihan::class);
    }

    /** Selisih waktu (menit) sebelum due_date pengingat ini harus dikirim. */
    public function minutesBefore(): int
    {
        return $this->remind_unit === self::UNIT_HOURS
            ? $this->remind_value * 60
            : $this->remind_value * 60 * 24;
    }

    /** Label ringkas dipakai UI/log, mis. "H-1" / "H-7". */
    public function label(): string
    {
        if ($this->remind_value === 0) {
            return 'Hari H';
        }

        $hari = $this->remind_unit === self::UNIT_HOURS
            ? max(1, intdiv($this->remind_value, 24))
            : $this->remind_value;

        return "H-{$hari}";
    }
}
