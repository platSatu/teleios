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
        if ($start < $this->jam_buka || $end > $this->jam_tutup) {
            return false;
        }

        if ($this->jam_istirahat_mulai && $this->jam_istirahat_selesai) {
            // Tumpang tindih kalau start < istirahat_selesai DAN end > istirahat_mulai.
            if ($start < $this->jam_istirahat_selesai && $end > $this->jam_istirahat_mulai) {
                return false;
            }
        }

        return true;
    }
}
