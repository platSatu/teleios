<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penugasan satu Pengajar (`users`) ke satu App\Models\JadwalKategori,
 * dengan hari & jam ketersediaannya SENDIRI untuk kategori itu. Lihat
 * migration create_jadwal_pengajar_kategori_table.php's docblock untuk
 * konteks lengkap restrukturisasi drill-down Jadwal (Branch -> Ruangan
 * -> Jam Operasional -> Mata Pelajaran / Bidang -> Kategori -> Pengajar
 * -> Student).
 *
 * Murni info ketersediaan (ditampilkan di form Add Student, lihat
 * App\Http\Controllers\Jadwal\JadwalStudentController::create()) --
 * TIDAK divalidasi silang ke App\Models\JadwalRutin, jadi menghapus
 * baris ini AMAN (tidak menghapus Jadwal Rutin/sesi yang sudah dibuat
 * pengajar itu, karena keduanya cuma sama-sama referensi ke
 * `users.id`, tidak saling FK).
 */
class JadwalPengajarKategori extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_pengajar_kategori';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'jadwal_kategori_id',
        'pengajar_id',
        'hari_bisa',
        'jam_mulai',
        'jam_selesai',
        'status',
    ];

    protected $casts = [
        'hari_bisa' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(JadwalKategori::class, 'jadwal_kategori_id');
    }

    public function pengajar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengajar_id');
    }

    /** Label hari Bahasa Indonesia yang dipilih, mis. "Senin, Rabu, Jumat". */
    public function hariBisaLabel(): string
    {
        return collect($this->hari_bisa ?? [])
            ->map(fn ($day) => JadwalRutin::HARI_LABELS[$day] ?? '?')
            ->implode(', ');
    }

    /** "H:i mulai" - "H:i selesai" dari kolom time (bisa "H:i" atau "H:i:s"). */
    public function jamRangeLabel(): string
    {
        return substr($this->jam_mulai, 0, 5).' - '.substr($this->jam_selesai, 0, 5);
    }
}
