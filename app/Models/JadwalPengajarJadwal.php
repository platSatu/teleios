<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu SLOT ketersediaan: satu hari + satu rentang jam,
 * milik satu App\Models\JadwalPengajarKategori (penugasan pengajar ke
 * Kategori). Satu penugasan BOLEH punya banyak baris di hari yang sama
 * (mis. Senin 10:00-12:00 dan Senin 17:00-19:00) -- lihat migration
 * create_jadwal_pengajar_kategori_jadwal_table.php's docblock untuk
 * kenapa dipecah dari kolom hari_bisa/jam_mulai/jam_selesai lama di
 * jadwal_pengajar_kategori (kasus lapangan: guru tidak available
 * nonstop pagi-sore seperti jam kantor).
 */
class JadwalPengajarJadwal extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_pengajar_kategori_jadwal';

    protected $fillable = [
        'jadwal_pengajar_kategori_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
    ];

    protected $casts = [
        'hari' => 'integer',
    ];

    public function pengajarKategori(): BelongsTo
    {
        return $this->belongsTo(JadwalPengajarKategori::class, 'jadwal_pengajar_kategori_id');
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
