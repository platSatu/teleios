<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu periode/invoice di bawah App\Models\TagihanCategory (contoh:
 * "Uang Sekolah Januari 2026"). Dibuat MANUAL oleh admin, bukan
 * digenerate otomatis -- lihat migration create_tagihan_table.php's
 * docblock untuk penjelasan lengkap (termasuk kenapa `amount`/
 * `pakai_denda` adalah salinan, bukan dibaca ulang dari category).
 */
class Tagihan extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'tagihan_category_id',
        'name',
        'amount',
        'due_date',
        'pakai_denda',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'pakai_denda' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TagihanCategory::class, 'tagihan_category_id');
    }

    /** Orang-orang yang ditagih untuk periode ini. */
    public function penerima(): HasMany
    {
        return $this->hasMany(TagihanPenerima::class);
    }

    /** Jadwal pengingat (H-7/H-3/dst) khusus tagihan ini. */
    public function reminderRules(): HasMany
    {
        return $this->hasMany(TagihanReminderRule::class);
    }
}
