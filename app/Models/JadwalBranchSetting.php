<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Jam Operasional" satu branch -- lihat migration
 * create_jadwal_branch_settings_table.php's docblock. Diedit lewat
 * App\Http\Controllers\Jadwal\JadwalBranchSettingController (halaman
 * singleton per branch, pola sama seperti JadwalReminderSettingController),
 * dibaca App\Console\Commands\GenerateJadwalRutinSesi untuk generate
 * sesi bulanan.
 */
class JadwalBranchSetting extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_branch_settings';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'hari_operasional',
        'jam_buka',
        'jam_tutup',
        'jam_istirahat_mulai',
        'jam_istirahat_selesai',
        'durasi_sesi_default_menit',
        'sesi_per_bulan_default',
        'status',
    ];

    protected $casts = [
        'hari_operasional' => 'array',
        'durasi_sesi_default_menit' => 'integer',
        'sesi_per_bulan_default' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice(): BelongsTo
    {
        return $this->belongsTo(BranchOffice::class);
    }

    /** Carbon::dayOfWeek $day (0=Minggu..6=Sabtu) termasuk hari operasional? */
    public function isHariOperasional(int $day): bool
    {
        return in_array($day, $this->hari_operasional ?? [], true);
    }

    /**
     * True kalau rentang waktu [$start,$end) (format "H:i") berada
     * dalam jam operasional DAN tidak menabrak jam istirahat.
     */
    public function isWithinOperationalHours(string $start, string $end): bool
    {
        // Normalisasi ke "H:i" (5 karakter) dulu sebelum dibandingkan --
        // kolom jam_buka/jam_tutup/jam_istirahat_* tersimpan sebagai string
        // "H:i:s" (8 karakter) dari DB, TIDAK di-cast Carbon di $casts di atas.
        // Membandingkan string "H:i" vs "H:i:s" langsung pakai operator </>
        // itu perbandingan lexicographic PHP biasa, bukan perbandingan waktu --
        // "10:00" (5 char) dianggap < "10:00:00" (8 char) karena "10:00" adalah
        // prefix dari string yang lebih panjang itu. Akibatnya sesi yang mulai
        // TEPAT di jam buka/istirahat selalu salah dianggap "di luar jam
        // operasional" (bug ditemukan 8 September 2026, dari laporan reschedule
        // murid Vallery yang gagal ikut sinkron ke Jadwal Rutin karena guard ini
        // salah menolak 10:00-10:30 padahal jam buka branch itu persis 10:00).
        // Menyamakan panjang kedua sisi ke 5 karakter membuat perbandingan
        // string ini kembali valid sebagai perbandingan waktu (format H:i
        // zero-padded selalu berurutan benar secara lexicographic).
        $start = substr($start, 0, 5);
        $end = substr($end, 0, 5);
        $jamBuka = substr($this->jam_buka, 0, 5);
        $jamTutup = substr($this->jam_tutup, 0, 5);

        if ($start < $jamBuka || $end > $jamTutup) {
            return false;
        }

        if ($this->jam_istirahat_mulai && $this->jam_istirahat_selesai) {
            $istirahatMulai = substr($this->jam_istirahat_mulai, 0, 5);
            $istirahatSelesai = substr($this->jam_istirahat_selesai, 0, 5);

            // Tumpang tindih kalau start < istirahat_selesai DAN end > istirahat_mulai.
            if ($start < $istirahatSelesai && $end > $istirahatMulai) {
                return false;
            }
        }

        return true;
    }
}
