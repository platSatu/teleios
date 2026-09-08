<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Penugasan satu Pengajar (`users`) ke satu App\Models\JadwalGrade.
 * MENGGANTIKAN peran App\Models\JadwalPengajarKategori (permintaan user
 * 8 September 2026: penugasan Pengajar + jam ketersediaan pindah dari
 * level Kategori ke level Grade yang baru) -- tabel
 * `jadwal_pengajar_kategori` LAMA SENGAJA TIDAK disentuh/dihapus sama
 * sekali (tetap ada sebagai data historis/frozen, lihat migration
 * create_jadwal_pengajar_grade_table.php's docblock), kode baru mulai
 * sekarang SELALU baca/tulis tabel INI, bukan yang lama.
 *
 * Hari & jam ketersediaannya ada di relasi jadwals() (App\Models\
 * JadwalPengajarGradeJadwal, tabel anak jadwal_pengajar_grade_jadwal)
 * -- SATU penugasan boleh punya BANYAK slot, termasuk banyak slot di
 * hari yang sama (kasus lapangan: pengajar tidak available nonstop
 * pagi-sore seperti jam kantor).
 *
 * Murni info ketersediaan (ditampilkan di form Add Student, lihat
 * App\Http\Controllers\Jadwal\JadwalStudentController::create()) --
 * TIDAK divalidasi silang ke App\Models\JadwalRutin, jadi menghapus
 * baris ini AMAN (tidak menghapus Jadwal Rutin/sesi yang sudah dibuat
 * pengajar itu, karena keduanya cuma sama-sama referensi ke
 * `users.id`, tidak saling FK).
 */
class JadwalPengajarGrade extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_pengajar_grade';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'jadwal_grade_id',
        'pengajar_id',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(JadwalGrade::class, 'jadwal_grade_id');
    }

    public function pengajar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengajar_id');
    }

    /** Slot hari & jam ketersediaan pengajar ini untuk Grade ini -- bisa lebih dari satu baris per hari. */
    public function jadwals(): HasMany
    {
        return $this->hasMany(JadwalPengajarGradeJadwal::class, 'jadwal_pengajar_grade_id')
            ->orderBy('hari')
            ->orderBy('jam_mulai');
    }

    /**
     * Slot dikelompokkan per hari, dipakai untuk tampilan ringkas di
     * index Pengajar & panel ketersediaan di form Add Student.
     * Contoh hasil satu item: ['label' => 'Senin', 'ranges' => ['10:00 - 12:00', '17:00 - 19:00']].
     *
     * @return Collection<int, array{label: string, ranges: array<int, string>}>
     */
    public function jadwalGroupedByHari(): Collection
    {
        return $this->jadwals
            ->groupBy('hari')
            ->map(fn ($slots, $hari) => [
                'label' => JadwalRutin::HARI_LABELS[$hari] ?? '?',
                'ranges' => $slots->map(fn ($slot) => $slot->jamRangeLabel())->all(),
            ])
            ->values();
    }

    /** Ringkasan satu baris teks, mis. "Senin: 10:00 - 12:00, 17:00 - 19:00 · Selasa: 09:00 - 11:00". Dipakai di tabel index yang sempit. */
    public function jadwalSummaryLabel(): string
    {
        return $this->jadwalGroupedByHari()
            ->map(fn ($group) => $group['label'].': '.implode(', ', $group['ranges']))
            ->implode(' · ');
    }
}
