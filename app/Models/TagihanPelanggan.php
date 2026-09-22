<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kontak yang ditagih lewat fitur Tagihan -- berdiri sendiri, sengaja
 * TIDAK dihubungkan ke App\Models\JadwalStudent/App\Models\WaCustomer
 * untuk saat ini. Lihat migration create_tagihan_pelanggan_table.php's
 * docblock.
 */
class TagihanPelanggan extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'tagihan_pelanggan';

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'name',
        'phone_number',
        'email',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    /** Category-category yang pelanggan ini "berlangganan". */
    public function categoryPelanggan(): HasMany
    {
        return $this->hasMany(TagihanCategoryPelanggan::class);
    }

    /** Semua baris tagihan yang pernah dibuat atas nama pelanggan ini. */
    public function tagihanPenerima(): HasMany
    {
        return $this->hasMany(TagihanPenerima::class);
    }
}
