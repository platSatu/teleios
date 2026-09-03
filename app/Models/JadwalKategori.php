<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Kategori" -- level BARU di bawah Kelas (App\Models\
 * JadwalMataPelajaran), tiap Kategori punya harga BULANAN + persentase
 * split company/pengajar sendiri. Lihat migration
 * create_jadwal_kategori_table.php's docblock.
 *
 * `harga_bulanan` (sebelumnya `harga_per_sesi` langsung, lihat migration
 * rename_harga_per_sesi_to_harga_bulanan_on_jadwal_kategori_table.php)
 * -- harga per SESI dihitung dari sini, dibagi jumlah sesi/bulan branch
 * (App\Models\JadwalBranchSetting::sesi_per_bulan_default) lewat
 * hargaPerSesi() di bawah, bukan disimpan langsung. `jadwal_kelas.
 * harga_sesi` tetap snapshot NILAI PER SESI (hasil hargaPerSesi() saat
 * sesi itu dibuat), bukan `harga_bulanan` mentah -- lihat
 * App\Console\Commands\GenerateJadwalRutinSesi.
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
        'harga_bulanan',
        'persentase_company',
        'persentase_pengajar',
        'status',
    ];

    protected $casts = [
        'harga_bulanan' => 'decimal:2',
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

    /**
     * Harga per SESI, dihitung dari harga_bulanan dibagi jumlah
     * sesi/bulan -- kirim `$sesiPerBulan` dari
     * JadwalBranchSetting::sesi_per_bulan_default branch yang relevan
     * (lihat App\Console\Commands\GenerateJadwalRutinSesi &
     * jadwal-rutin/_form.blade.php's pemakaian). Kalau tidak dikirim
     * (mis. listing lintas-branch di jadwal-kategori/index.blade.php
     * yang tidak punya satu branch spesifik), fallback ke 4 sesuai
     * default umum (CLAUDE.md item #15 spec poin 6).
     */
    public function hargaPerSesi(?int $sesiPerBulan = null): float
    {
        $pembagi = $sesiPerBulan ?: 4;

        return round(((float) $this->harga_bulanan) / $pembagi, 2);
    }
}
