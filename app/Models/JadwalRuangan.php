<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Ruangan" per branch -- murni info (nama + catatan kegunaan), tidak
 * mengunci ke Kelas/Kategori tertentu. Lihat migration
 * create_jadwal_ruangan_table.php's docblock.
 */
class JadwalRuangan extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_ruangan';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'name',
        'catatan_kegunaan',
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

    public function jadwalRutins(): HasMany
    {
        return $this->hasMany(JadwalRutin::class, 'jadwal_ruangan_id');
    }
}
