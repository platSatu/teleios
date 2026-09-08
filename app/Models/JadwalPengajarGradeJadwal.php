<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu SLOT ketersediaan: satu hari + satu rentang jam,
 * milik satu App\Models\JadwalPengajarGrade (penugasan pengajar ke
 * Grade). Satu penugasan BOLEH punya banyak baris di hari yang sama
 * (mis. Senin 10:00-12:00 dan Senin 17:00-19:00) -- struktur & alasan
 * IDENTIK App\Models\JadwalPengajarJadwal (tabel
 * jadwal_pengajar_kategori_jadwal) yang lama, cuma parent-nya sekarang
 * Grade, bukan Kategori.
 */
class JadwalPengajarGradeJadwal extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_pengajar_grade_jadwal';

    protected $fillable = [
        'jadwal_pengajar_grade_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
    ];

    protected $casts = [
        'hari' => 'integer',
    ];

    public function pengajarGrade(): BelongsTo
    {
        return $this->belongsTo(JadwalPengajarGrade::class, 'jadwal_pengajar_grade_id');
    }

    /** Label hari Bahasa Indonesia, mis. "Senin". */
    public function hariLabel(): string
    {
        return JadwalRutin::HARI_LABELS[$this->hari] ?? '?';
    }

    /** "H:i" - "H:i" dari kolom time (bisa "H:i" atau "H:i:s"). */
    public function jamRangeLabel(): string
    {
        return substr($this->jam_mulai, 0, 5).' - '.substr($this->jam_selesai, 0, 5);
    }
}
