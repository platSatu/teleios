<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris template pengingat milik App\Models\TagihanCategory --
 * lihat migration create_tagihan_category_reminder_rule_table.php's
 * docblock: ini template yang disalin ke App\Models\TagihanReminderRule
 * tiap kali Tagihan baru dibuat, bukan yang dibaca langsung saat
 * mengirim pengingat.
 */
class TagihanCategoryReminderRule extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan_category_reminder_rule';

    public const UNIT_HOURS = 'hours';

    public const UNIT_DAYS = 'days';

    protected $fillable = [
        'tagihan_category_id',
        'remind_value',
        'remind_unit',
    ];

    protected $casts = [
        'remind_value' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TagihanCategory::class, 'tagihan_category_id');
    }
}
