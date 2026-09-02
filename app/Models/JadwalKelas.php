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
 *
 * **Jadwal v2** (CLAUDE.md item #15) menambahkan: baris ini bisa jadi
 * hasil AUTO-GENERATE dari satu App\Models\JadwalRutin
 * (jadwal_rutin_id, nullable -- null berarti dibuat manual seperti
 * sebelumnya), dengan kolom SNAPSHOT harga/persentase/durasi/kategori/
 * ruangan yang di-copy dari Kategori pada saat generate (lihat migration
 * add_jadwal_v2_columns_to_jadwal_kelas_table.php's docblock untuk
 * kenapa snapshot, bukan selalu baca live). `attendance_status` juga
 * bertambah 1 opsi: ATTENDANCE_IZIN ("Izin/Sakit -- dapat pengganti").
 * `pengganti_dari_sesi_id` menandai baris ini sebagai SESI PENGGANTI
 * untuk sesi lain yang izin/sakit.
 */
class JadwalKelas extends Model
{
    use HasUuidPrimaryKey;

    protected $table = 'jadwal_kelas';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_INACTIVE];

    public const ATTENDANCE_HADIR = 'hadir';

    /** Tidak hadir tanpa keterangan -- hangus, TIDAK ada pengganti. Pengajar tetap dibayar penuh (tetap hadir mengajar). */
    public const ATTENDANCE_TIDAK_HADIR = 'tidak_hadir';

    /** Izin/sakit -- berhak dapat sesi pengganti (lihat pengganti_dari_sesi_id). */
    public const ATTENDANCE_IZIN = 'izin';

    public const ATTENDANCE_STATUSES = [
        self::ATTENDANCE_HADIR,
        self::ATTENDANCE_TIDAK_HADIR,
        self::ATTENDANCE_IZIN,
    ];

    /** attendance_status yang pengajarnya tetap dibayar penuh walau murid tidak hadir. */
    public const ATTENDANCE_TETAP_DIBAYAR = [self::ATTENDANCE_HADIR, self::ATTENDANCE_TIDAK_HADIR];

    protected $fillable = [
        'company_id',
        'branch_office_id',
        'jadwal_mata_pelajaran_id',
        'pengajar_id',
        'student_id',
        'jadwal_rutin_id',
        'jadwal_kategori_id',
        'jadwal_ruangan_id',
        'start_time',
        'end_time',
        'duration_minutes',
        'harga_sesi',
        'persentase_company',
        'persentase_pengajar',
        'pengganti_dari_sesi_id',
        'status',
        'attendance_status',
        'attendance_notes',
        'description',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_minutes' => 'integer',
        'harga_sesi' => 'decimal:2',
        'persentase_company' => 'decimal:2',
        'persentase_pengajar' => 'decimal:2',
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

    public function jadwalRutin()
    {
        return $this->belongsTo(JadwalRutin::class, 'jadwal_rutin_id');
    }

    public function kategori()
    {
        return $this->belongsTo(JadwalKategori::class, 'jadwal_kategori_id');
    }

    public function ruangan()
    {
        return $this->belongsTo(JadwalRuangan::class, 'jadwal_ruangan_id');
    }

    /** Sesi ASLI yang digantikan oleh baris ini (kalau baris ini adalah sesi pengganti). */
    public function penggantiDariSesi()
    {
        return $this->belongsTo(self::class, 'pengganti_dari_sesi_id');
    }

    /** Sesi PENGGANTI yang dibuat dari baris ini (kalau baris ini yang izin/sakit). */
    public function sesiPengganti()
    {
        return $this->hasOne(self::class, 'pengganti_dari_sesi_id');
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

    /** Nominal fee bagian company untuk sesi ini, dari snapshot harga_sesi/persentase_company. */
    public function feeCompany(): float
    {
        if ($this->harga_sesi === null || $this->persentase_company === null) {
            return 0.0;
        }

        return round(((float) $this->harga_sesi) * ((float) $this->persentase_company) / 100, 2);
    }

    /** Nominal fee bagian pengajar untuk sesi ini, dari snapshot harga_sesi/persentase_pengajar. Dihitung penuh selama attendance_status ada di ATTENDANCE_TETAP_DIBAYAR. */
    public function feePengajar(): float
    {
        if ($this->harga_sesi === null || $this->persentase_pengajar === null) {
            return 0.0;
        }

        return round(((float) $this->harga_sesi) * ((float) $this->persentase_pengajar) / 100, 2);
    }
}
