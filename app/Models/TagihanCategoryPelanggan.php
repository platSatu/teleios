<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Langganan" satu App\Models\TagihanPelanggan ke satu
 * App\Models\TagihanCategory -- checklist-nya user. Lihat migration
 * create_tagihan_category_pelanggan_table.php's docblock untuk alasan
 * desain lengkap (kenapa status dinonaktifkan, bukan dihapus, saat
 * pelanggan berhenti langganan).
 */
class TagihanCategoryPelanggan extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan_category_pelanggan';

    protected $fillable = [
        'tagihan_category_id',
        'tagihan_pelanggan_id',
        'nominal_override',
        'status',
    ];

    protected $casts = [
        'nominal_override' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TagihanCategory::class, 'tagihan_category_id');
    }

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(TagihanPelanggan::class, 'tagihan_pelanggan_id');
    }
}
