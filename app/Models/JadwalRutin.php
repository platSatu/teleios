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

    /**
     * Jam mulai + durasi efektif -> jam selesai "H:i".
     *
     * Update 4 September 2026 (bug fix, laporan user: 500 Server Error
     * waktu update jadwal Student): SEBELUMNYA pakai
     * `Carbon::createFromFormat('H:i:s', $this->jam_mulai)` yang STRICT
     * -- cocok kalau `jam_mulai` baru saja dibaca ULANG dari database
     * (kolom `time` MySQL selalu balik "H:i:s"), TAPI meledak
     * (InvalidFormatException, jadi 500 mentah karena tidak ada
     * try/catch di pemanggilnya) kalau dipanggil pada instance yang
     * BARU SAJA dibuat lewat `JadwalRutin::create([..., 'jam_mulai' =>
     * $jamMulai, ...])` di memory yang sama TANPA refresh dari DB dulu
     * -- atribut in-memory-nya masih persis string yang dikirim ke
     * create() (mis. "10:00", format "H:i" TANPA detik, lihat
     * App\Http\Controllers\Jadwal\JadwalStudentController::
     * createRutinFromSlots()'s `$jamMulai` dari splitSlotIntoChunks()).
     * Ketemu lewat App\Services\Jadwal\JadwalScheduleChangeNotifier::
     * snapshotRutin() -> rutinAdded() yang dipanggil PERSIS pada
     * instance sesaat setelah JadwalRutin::create() -- baris baru fitur
     * histori jadwal (4 September 2026).
     *
     * `Carbon::parse()` (bukan createFromFormat) menerima KEDUA bentuk
     * ("10:00" maupun "10:00:00") tanpa peduli sumbernya baru dibuat
     * di memory atau hasil query DB -- perbaikan di titik ini otomatis
     * melindungi SEMUA pemanggil jamSelesai() yang ada (lihat juga
     * App\Services\Jadwal\JadwalRutinConflictService & JadwalStudentController's
     * pemakaian lain, yang kebetulan semuanya baca dari query DB jadi
     * tidak pernah kena bug ini -- tapi baiknya method ini sendiri yang
     * robust, bukan mengandalkan tiap pemanggil selalu re-query).
     */
    public function jamSelesai(): string
    {
        return \Carbon\Carbon::parse($this->jam_mulai)
            ->addMinutes($this->effectiveDurationMinutes())
            ->format('H:i');
    }
}
