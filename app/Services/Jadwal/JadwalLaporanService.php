<?php

namespace App\Services\Jadwal;

use App\Models\JadwalGrade;
use App\Models\JadwalKategori;
use App\Models\JadwalKelas;
use App\Models\JadwalMataPelajaran;
use App\Models\JadwalStudent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data Laporan Jadwal (redesign 8 Oktober 2026): 4 box ringkasan + tab
 * Student & tab Pengajar. Satu sumber untuk halaman
 * (JadwalLaporanController) DAN export Excel (JadwalLaporanExport),
 * jadi angka di layar & di file selalu sama.
 *
 * Aturan hitung:
 * - Hanya sesi aktif milik murid yang MASIH AKTIF (keputusan user
 *   4 September 2026: murid nonaktif tidak dihitung lagi di laporan).
 * - Tab Student: semua sesi di rentang tanggal, absensi dihitung per
 *   status (Hadir / Tidak Hadir / Izin / Belum diabsen).
 * - Tab Pengajar: HANYA sesi yang dibayar (Hadir + Tidak Hadir,
 *   JadwalKelas::ATTENDANCE_TETAP_DIBAYAR) -- jumlah sesi, jam & fee
 *   dari sesi yang sama. Fee = snapshot harga_sesi × persentase_pengajar
 *   per sesi (JadwalKelas::feePengajar(), asalnya dari Grade).
 * - Omset: potensi BULANAN = tiap murid aktif × total harga bulanan Grade
 *   dari Jadwal Rutin aktifnya (StudentTagihanLink::monthlyAmounts) --
 *   tidak ikut filter tanggal.
 */
class JadwalLaporanService
{
    public function __construct(private StudentTagihanLink $tagihanLink)
    {
    }

    /**
     * @return array{
     *   totalMurid: int, muridBaru: int, omset: int,
     *   mataPelajaranCount: int, kategoriCount: int, gradeCount: int,
     *   students: Collection<int, array>, pengajar: Collection<int, array>,
     * }
     */
    public function build(string $companyId, ?string $branchOfficeId, Carbon $from, Carbon $to): array
    {
        $activeStudentIds = JadwalStudent::where('company_id', $companyId)
            ->where('status', JadwalStudent::STATUS_ACTIVE)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->pluck('id');

        $muridBaru = JadwalStudent::where('company_id', $companyId)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->whereBetween('created_at', [$from, $to])
            ->count();

        // Mata Pelajaran tanpa branch berlaku untuk semua branch.
        $mataPelajaranIds = JadwalMataPelajaran::where('company_id', $companyId)
            ->where('status', JadwalMataPelajaran::STATUS_ACTIVE)
            ->when($branchOfficeId, fn ($q) => $q->where(fn ($w) => $w->where('branch_office_id', $branchOfficeId)->orWhereNull('branch_office_id')))
            ->pluck('id');

        $kategoriIds = JadwalKategori::where('company_id', $companyId)
            ->where('status', JadwalKategori::STATUS_ACTIVE)
            ->whereIn('jadwal_mata_pelajaran_id', $mataPelajaranIds)
            ->pluck('id');

        $gradeCount = JadwalGrade::where('company_id', $companyId)
            ->where('status', JadwalGrade::STATUS_ACTIVE)
            ->whereIn('jadwal_kategori_id', $kategoriIds)
            ->count();

        $sesi = JadwalKelas::where('company_id', $companyId)
            ->where('status', JadwalKelas::STATUS_ACTIVE)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->whereBetween('start_time', [$from, $to])
            ->whereHas('student', fn ($q) => $q->where('status', JadwalStudent::STATUS_ACTIVE))
            ->with(['student:id,name', 'pengajar:id,name', 'ruangan:id,name', 'mataPelajaran:id,name', 'kategori:id,name', 'grade:id,name'])
            ->get();

        return [
            'totalMurid' => $activeStudentIds->count(),
            'muridBaru' => $muridBaru,
            'omset' => array_sum($this->tagihanLink->monthlyAmounts($companyId, $activeStudentIds)),
            'mataPelajaranCount' => $mataPelajaranIds->count(),
            'kategoriCount' => $kategoriIds->count(),
            'gradeCount' => $gradeCount,
            'students' => $this->studentRows($sesi),
            'pengajar' => $this->pengajarRows($sesi),
        ];
    }

    /** Satu baris per murid × pengajar × ruangan × kelas × kategori × grade. */
    private function studentRows(Collection $sesi): Collection
    {
        return $this->groupRows($sesi, ['student_id', 'pengajar_id', 'jadwal_ruangan_id', 'jadwal_mata_pelajaran_id', 'jadwal_kategori_id', 'jadwal_grade_id'])
            ->map(fn (Collection $rows) => $this->labels($rows->first()) + [
                'hadir' => $rows->where('attendance_status', JadwalKelas::ATTENDANCE_HADIR)->count(),
                'tidak_hadir' => $rows->where('attendance_status', JadwalKelas::ATTENDANCE_TIDAK_HADIR)->count(),
                'izin' => $rows->where('attendance_status', JadwalKelas::ATTENDANCE_IZIN)->count(),
                'belum' => $rows->whereNull('attendance_status')->count(),
            ])
            ->sortBy(fn ($r) => mb_strtolower($r['murid'].'|'.$r['pengajar'].'|'.$r['kelas']))
            ->values();
    }

    /** Satu baris per pengajar × ruangan × kelas × kategori × grade, dari sesi yang dibayar. */
    private function pengajarRows(Collection $sesi): Collection
    {
        $dibayar = $sesi->whereNotNull('pengajar_id')
            ->whereIn('attendance_status', JadwalKelas::ATTENDANCE_TETAP_DIBAYAR);

        return $this->groupRows($dibayar, ['pengajar_id', 'jadwal_ruangan_id', 'jadwal_mata_pelajaran_id', 'jadwal_kategori_id', 'jadwal_grade_id'])
            ->map(fn (Collection $rows) => $this->labels($rows->first()) + [
                'jumlah_sesi' => $rows->count(),
                'total_menit' => (int) $rows->sum(fn (JadwalKelas $k) => $k->duration_minutes
                    ?? ($k->start_time && $k->end_time ? $k->start_time->diffInMinutes($k->end_time) : 0)),
                'fee_pengajar' => round($rows->sum(fn (JadwalKelas $k) => $k->feePengajar()), 2),
            ])
            ->sortBy(fn ($r) => mb_strtolower($r['pengajar'].'|'.$r['kelas'].'|'.$r['kategori']))
            ->values();
    }

    private function groupRows(Collection $sesi, array $keys): Collection
    {
        return $sesi->groupBy(fn (JadwalKelas $k) => implode('|', array_map(fn ($key) => (string) $k->{$key}, $keys)));
    }

    private function labels(JadwalKelas $k): array
    {
        return [
            'murid' => $k->student?->name ?? '-',
            'pengajar' => $k->pengajar?->name ?? '-',
            'ruangan' => $k->ruangan?->name ?? '-',
            'kelas' => $k->mataPelajaran?->name ?? '-',
            'kategori' => $k->kategori?->name ?? '-',
            'grade' => $k->grade?->name ?? '-',
        ];
    }

    /** "2 jam 30 menit" -- dipakai halaman & export. */
    public static function formatJam(int $menit): string
    {
        $jam = intdiv($menit, 60);
        $sisa = $menit % 60;

        return trim(($jam ? "{$jam} jam " : '').($sisa || ! $jam ? "{$sisa} menit" : ''));
    }
}
