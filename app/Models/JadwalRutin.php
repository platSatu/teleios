<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Jadwal Rutin" murid -- cetakan jadwal mingguan berulang: Kategori +
 * Pengajar + Ruangan (opsional) + Hari + Jam mulai + Durasi. Lihat
 * migration create_jadwal_rutin_table.php's docblock untuk desain
 * lengkap & alasan validasi bentrok dicek di App\Http\Controllers\
 * Jadwal\JadwalRutinController, bukan di sini.
 */
class JadwalRutin extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_rutin';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    /** Label hari Bahasa Indonesia, index selaras Carbon::dayOfWeek (0=Minggu). */
    public const HARI_LABELS = [
        0 => 'Minggu',
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'student_id',
        'jadwal_kategori_id',
        'pengajar_id',
        'jadwal_ruangan_id',
        'hari',
        'jam_mulai',
        'durasi_menit',
        'efektif_mulai',
        'efektif_selesai',
        'status',
    ];

    protected $casts = [
        'hari' => 'integer',
        'durasi_menit' => 'integer',
        'efektif_mulai' => 'date',
        'efektif_selesai' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(JadwalStudent::class, 'student_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(JadwalKategori::class, 'jadwal_kategori_id');
    }

    public function pengajar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengajar_id');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(JadwalRuangan::class, 'jadwal_ruangan_id');
    }

    public function sesi(): HasMany
    {
        return $this->hasMany(JadwalKelas::class, 'jadwal_rutin_id');
    }

    public function hariLabel(): string
    {
        return self::HARI_LABELS[$this->hari] ?? '-';
    }

    /** Durasi efektif (menit) -- durasi_menit sendiri, atau default branch kalau kosong. */
    public function effectiveDurationMinutes(): int
    {
        return $this->durasi_menit ?: (int) ($this->branchOffice?->jadwalBranchSetting?->durasi_sesi_default_menit ?? 30);
    }

    /** Jam mulai "H:i" + durasi efektif -> jam selesai "H:i". */
    public function jamSelesai(): string
    {
        return \Carbon\Carbon::createFromFormat('H:i:s', $this->jam_mulai)
            ->addMinutes($this->effectiveDurationMinutes())
            ->format('H:i');
    }
}
