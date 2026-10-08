<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Kategori" -- level di bawah Kelas (App\Models\JadwalMataPelajaran),
 * murni pengelompokan Grade (nama + status). Harga bulanan & persentase
 * fee pengajar ada di App\Models\JadwalGrade (8 Oktober 2026). Kolom lama
 * harga_bulanan/persentase_* di tabel jadwal_kategori dibiarkan (nullable,
 * riwayat) tapi tidak lagi diisi atau dibaca kode mana pun.
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
        'status',
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

    /**
     * Pengajar yang ditugaskan ke Kategori ini + jam ketersediaannya
     * (restrukturisasi drill-down Jadwal 14 September 2026, lihat
     * App\Models\JadwalPengajarKategori's docblock). Level baru di
     * antara Kategori dan Student.
     *
     * LEGACY sejak fitur Grade (8 September 2026) -- relasi ini
     * dibiarkan apa adanya untuk data historis, kode BARU tidak lagi
     * membuat baris baru lewat sini. Lihat grades() di bawah.
     */
    public function pengajarKategoris(): HasMany
    {
        return $this->hasMany(JadwalPengajarKategori::class, 'jadwal_kategori_id');
    }

    /**
     * Grade di bawah Kategori ini (mis. "Grade A", "Grade B") --
     * pemilik BARU harga bulanan, persentase split, & penugasan
     * Pengajar (lihat App\Models\JadwalGrade's docblock).
     */
    public function grades(): HasMany
    {
        return $this->hasMany(JadwalGrade::class, 'jadwal_kategori_id');
    }
}
