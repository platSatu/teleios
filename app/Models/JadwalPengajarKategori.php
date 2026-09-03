<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Penugasan satu Pengajar (`users`) ke satu App\Models\JadwalKategori.
 * Lihat migration create_jadwal_pengajar_kategori_table.php's docblock
 * untuk konteks lengkap restrukturisasi drill-down Jadwal (Branch ->
 * Ruangan -> Jam Operasional -> Mata Pelajaran / Bidang -> Kategori ->
 * Pengajar -> Student).
 *
 * Hari & jam ketersediaannya ada di relasi jadwals() (App\Models\
 * JadwalPengajarJadwal, tabel anak jadwal_pengajar_kategori_jadwal) --
 * SATU penugasan boleh punya BANYAK slot, termasuk banyak slot di hari
 * yang sama (kasus lapangan: pengajar tidak available nonstop
 * pagi-sore seperti jam kantor). Lihat migration tabel anak untuk
 * detail kenapa dipecah dari kolom hari_bisa/jam_mulai/jam_selesai yang
 * dulu ada langsung di sini.
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
        'status',
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

    /** Slot hari & jam ketersediaan pengajar ini untuk Kategori ini -- bisa lebih dari satu baris per hari. */
    public function jadwals(): HasMany
    {
        return $this->hasMany(JadwalPengajarJadwal::class, 'jadwal_pengajar_kategori_id')
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
