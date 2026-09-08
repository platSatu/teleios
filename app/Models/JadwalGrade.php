<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Grade" -- level BARU di bawah App\Models\JadwalKategori (permintaan
 * user 8 September 2026: "bidang bass, category jass, grade 1 atau
 * apapun bahasa nya bisa grade A"). Menggantikan peran Kategori sebagai
 * pemilik harga BULANAN + persentase split company/pengajar + anchor
 * penugasan Pengajar -- lihat migration create_jadwal_grade_table.php's
 * docblock untuk alasan lengkap & kenapa kolom sejenis di JadwalKategori
 * SENGAJA dibiarkan (bukan dihapus/dipindah).
 *
 * `jadwal_kategori_id` di JadwalRutin/JadwalKelas TETAP dipertahankan
 * apa adanya (auto-diisi dari grade->kategori->id) supaya SEMUA query
 * lama yang mengandalkan kolom itu (badge count, filter Bidang/Mata
 * Pelajaran, dsb -- lihat App\Services\Jadwal\JadwalCountsService)
 * tetap jalan tanpa perubahan. `jadwal_grade_id` ini yang jadi sumber
 * PRICING baru (lihat App\Services\Jadwal\JadwalRutinSesiGenerator) &
 * penentu keanggotaan Pengajar (lewat App\Models\JadwalPengajarGrade).
 *
 * Data Kategori yang sudah ada sebelum fitur ini SENGAJA TIDAK
 * dipisah namanya (mis. "Piano Classic Level 1" tetap satu nama utuh,
 * user eksplisit menolak name-parsing) -- setiap Kategori lama dapat
 * SATU Grade default hasil backfill (lihat migration
 * backfill_default_jadwal_grade_for_existing_kategori.php) yang
 * membungkus harga/persentase/penugasan-pengajar lamanya apa adanya.
 */
class JadwalGrade extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_grade';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'jadwal_kategori_id',
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

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(JadwalKategori::class, 'jadwal_kategori_id');
    }

    public function jadwalRutins(): HasMany
    {
        return $this->hasMany(JadwalRutin::class, 'jadwal_grade_id');
    }

    /** Pengajar yang ditugaskan ke Grade ini + jam ketersediaannya. Level baru di antara Grade dan Student. */
    public function pengajarGrades(): HasMany
    {
        return $this->hasMany(JadwalPengajarGrade::class, 'jadwal_grade_id');
    }

    /**
     * Harga per SESI, dihitung dari harga_bulanan dibagi jumlah
     * sesi/bulan -- kirim `$sesiPerBulan` dari
     * JadwalBranchSetting::sesi_per_bulan_default branch yang relevan.
     * Kalau tidak dikirim, fallback ke 4 sesuai default umum (identik
     * App\Models\JadwalKategori::hargaPerSesi()).
     */
    public function hargaPerSesi(?int $sesiPerBulan = null): float
    {
        $pembagi = $sesiPerBulan ?: 4;

        return round(((float) $this->harga_bulanan) / $pembagi, 2);
    }
}
