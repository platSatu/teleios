<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris jadwal kelas kursus — 1 pengajar + 1 murid + rentang
 * waktu, terhubung opsional ke satu App\Models\JadwalMataPelajaran.
 * Lihat App\Http\Controllers\Jadwal\JadwalKelasController untuk CRUD-nya.
 * `pengajar_id` FK ke `users`, `student_id` FK ke App\Models\
 * JadwalStudent (roster sendiri, bukan `users`) — lihat migration
 * create_jadwal_kelas_table.php's docblock.
 *
 * `attendance_status`/`attendance_notes`: kehadiran student di sesi
 * ini (lihat migration add_attendance_to_jadwal_kelas_table.php's
 * docblock) — terpisah dari `status` yang artinya jadwal ini
 * aktif/nonaktif, bukan soal hadir/tidak. Index-nya (lihat
 * jadwal-kelas/index.blade.php) menampilkan baris-baris dengan
 * pengajar+mata-pelajaran sama sebagai satu grup (sel Pengajar/Mata
 * Pelajaran digabung ala Excel) walau datanya tetap 1 baris per
 * student -- bukan restrukturisasi ke "kelas grup".
 */
class JadwalKelas extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_kelas';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    public const ATTENDANCE_HADIR = 'hadir';

    public const ATTENDANCE_TIDAK_HADIR = 'tidak_hadir';

    public const ATTENDANCE_STATUSES = [self::ATTENDANCE_HADIR, self::ATTENDANCE_TIDAK_HADIR];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'jadwal_mata_pelajaran_id',
        'pengajar_id',
        'student_id',
        'start_time',
        'end_time',
        'status',
        'attendance_status',
        'attendance_notes',
        'description',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branchOffice()
    {
        return $this->belongsTo(BranchOffice::class);
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(JadwalMataPelajaran::class, 'jadwal_mata_pelajaran_id');
    }

    public function pengajar()
    {
        return $this->belongsTo(User::class, 'pengajar_id');
    }

    public function student()
    {
        return $this->belongsTo(JadwalStudent::class, 'student_id');
    }

    /**
     * Jejak klaim/kirim pengingat WA untuk baris ini -- lihat
     * App\Models\JadwalKelasReminderLog & App\Console\Commands\
     * DispatchDueJadwalReminders. Selalu paling banyak satu (unique
     * jadwal_kelas_id), karena satu Jadwal Kelas = satu sesi yang cuma
     * butuh satu pengingat, bukan jadwal berulang.
     */
    public function reminderLog()
    {
        return $this->hasOne(JadwalKelasReminderLog::class);
    }
}
