<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu tingkat aturan denda keterlambatan milik App\Models\TagihanCategory
 * -- lihat migration create_tagihan_denda_tier_table.php's docblock
 * untuk penjelasan lengkap `mulai_hari_ke`/`sampai_hari_ke` (offset dari
 * due_date, bukan tanggal kalender) dan contoh perhitungannya.
 */
class TagihanDendaTier extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan_denda_tier';

    public const TIPE_PERSEN_PER_HARI = 'persen_per_hari';

    public const TIPE_FLAT = 'flat';

    public const TIPE_LIST = [self::TIPE_PERSEN_PER_HARI, self::TIPE_FLAT];

    public const FREKUENSI_PER_HARI = 'per_hari';

    public const FREKUENSI_SEKALI = 'sekali';

    protected $fillable = [
        'tagihan_category_id',
        'urutan',
        'mulai_hari_ke',
        'sampai_hari_ke',
        'tipe',
        'nilai',
        'frekuensi_flat',
    ];

    protected $casts = [
        'urutan' => 'integer',
        'mulai_hari_ke' => 'integer',
        'sampai_hari_ke' => 'integer',
        'nilai' => 'decimal:4',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TagihanCategory::class, 'tagihan_category_id');
    }

    public function isFlat(): bool
    {
        return $this->tipe === self::TIPE_FLAT;
    }

    public function isPersenPerHari(): bool
    {
        return $this->tipe === self::TIPE_PERSEN_PER_HARI;
    }

    /**
     * Apakah tier ini berlaku untuk keterlambatan sejumlah $hariTelat
     * hari (offset dari due_date, 0 = hari H). `sampai_hari_ke` NULL
     * berarti tier ini berlaku seterusnya begitu $hariTelat >=
     * mulai_hari_ke.
     */
    public function berlakuUntuk(int $hariTelat): bool
    {
        if ($hariTelat < $this->mulai_hari_ke) {
            return false;
        }

        return $this->sampai_hari_ke === null || $hariTelat <= $this->sampai_hari_ke;
    }
}
