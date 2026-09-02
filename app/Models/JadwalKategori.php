<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Kategori" -- level BARU di bawah Kelas (App\Models\
 * JadwalMataPelajaran), tiap Kategori punya harga per sesi + persentase
 * split company/pengajar sendiri. Lihat migration
 * create_jadwal_kategori_table.php's docblock.
 */
class JadwalKategori extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_kategori';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'jadwal_mata_pelajaran_id',
        'name',
        'harga_per_sesi',
        'persentase_company',
        'persentase_pengajar',
        'status',
    ];

    protected $casts = [
        'harga_per_sesi' => 'decimal:2',
        'persentase_company' => 'decimal:2',
        'persentase_pengajar' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function mataPelajaran(): BelongsTo
    {
        return $this->belongsTo(JadwalMataPelajaran::class, 'jadwal_mata_pelajaran_id');
    }

    public function jadwalRutins(): HasMany
    {
        return $this->hasMany(JadwalRutin::class, 'jadwal_kategori_id');
    }

    /** Nominal fee bagian company untuk satu sesi Kategori ini. */
    public function feeCompanyPerSesi(): float
    {
        return round(((float) $this->harga_per_sesi) * ((float) $this->persentase_company) / 100, 2);
    }

    /** Nominal fee bagian pengajar untuk satu sesi Kategori ini. */
    public function feePengajarPerSesi(): float
    {
        return round(((float) $this->harga_per_sesi) * ((float) $this->persentase_pengajar) / 100, 2);
    }
}
