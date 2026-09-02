<?php

namespace App\Services\Jadwal;

use App\Models\JadwalKelas;
use App\Models\JadwalKelasRescheduleRequest;
use App\Models\JadwalStudent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data untuk Laporan Harian & Bulanan Jadwal v2 (CLAUDE.md item #15
 * spec poin 12 & 13) -- dipakai bersama oleh App\Http\Controllers\
 * Jadwal\JadwalLaporanController (tampilan halaman) dan
 * App\Exports\Jadwal\* (download Excel), supaya angka yang tampil di
 * layar dan yang di-export SELALU sama persis (satu sumber query).
 *
 * Fee & jam mengajar HANYA dihitung dari sesi yang attendance_status-nya
 * SUDAH DITANDAI 'hadir' atau 'tidak_hadir' (App\Models\JadwalKelas::
 * ATTENDANCE_TETAP_DIBAYAR) -- keduanya tetap membayar pengajar penuh
 * (lihat spec poin 7: "tidak hadir" murid ttp dibayar krn pengajar
 * tetap hadir). Sesi 'izin' TIDAK dihitung di tanggal aslinya (fee-nya
 * pindah ke sesi PENGGANTI, dihitung di tanggal sesi pengganti itu
 * sendiri kalau attendance-nya sudah ditandai juga). Sesi yang belum
 * ditandai attendance sama sekali (mis. tanggal di masa depan) juga
 * belum dihitung -- baru "diakui" begitu admin menandai kehadirannya.
 */
class JadwalLaporanService
{
    /**
     * Rekap SATU HARI: semua sesi aktif tanggal itu, untuk Laporan
     * Harian (spec poin 12 -- "kelas mana aja yang terpakai, daftar
     * murid nya, dan jam nya").
     *
     * @return Collection<int, JadwalKelas>
     */
    public function harian(string $companyId, ?string $branchOfficeId, Carbon $date): Collection
    {
        return JadwalKelas::where('company_id', $companyId)
            ->where('status', JadwalKelas::STATUS_ACTIVE)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->whereDate('start_time', $date)
            ->with(['mataPelajaran:id,name', 'kategori:id,name', 'ruangan:id,name', 'pengajar:id,name', 'student:id,name'])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Rekap SATU BULAN untuk Laporan Bulanan (spec poin 13): jumlah
     * murid aktif/baru, jumlah reschedule, total fee (company vs
     * pengajar), lama mengajar per pengajar.
     *
     * @return array{
     *   activeStudentCount: int, newStudentCount: int, rescheduleCount: int,
     *   feeCompanyTotal: float, feePengajarTotal: float,
     *   perPengajar: Collection<int, array{pengajar_id: string, nama: string, jumlah_sesi: int, total_menit: int, fee_pengajar: float}>,
     * }
     */
    public function bulanan(string $companyId, ?string $branchOfficeId, Carbon $monthStart, Carbon $monthEnd): array
    {
        $activeStudentCount = JadwalStudent::where('company_id', $companyId)
            ->where('status', JadwalStudent::STATUS_ACTIVE)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->count();

        $newStudentCount = JadwalStudent::where('company_id', $companyId)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $rescheduleCount = JadwalKelasRescheduleRequest::where('company_id', $companyId)
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->count();

        $paidSesi = JadwalKelas::where('company_id', $companyId)
            ->where('status', JadwalKelas::STATUS_ACTIVE)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->whereBetween('start_time', [$monthStart, $monthEnd])
            ->whereIn('attendance_status', JadwalKelas::ATTENDANCE_TETAP_DIBAYAR)
            ->with('pengajar:id,name')
            ->get();

        $feeCompanyTotal = 0.0;
        $feePengajarTotal = 0.0;

        $perPengajar = $paidSesi
            ->groupBy('pengajar_id')
            ->map(function (Collection $sesiPengajar) use (&$feeCompanyTotal, &$feePengajarTotal) {
                $feeCompany = $sesiPengajar->sum(fn (JadwalKelas $k) => $k->feeCompany());
                $feePengajar = $sesiPengajar->sum(fn (JadwalKelas $k) => $k->feePengajar());
                $totalMenit = (int) $sesiPengajar->sum(fn (JadwalKelas $k) => $k->duration_minutes
                    ?? ($k->start_time && $k->end_time ? $k->start_time->diffInMinutes($k->end_time) : 0));

                $feeCompanyTotal += $feeCompany;
                $feePengajarTotal += $feePengajar;

                return [
                    'pengajar_id' => $sesiPengajar->first()->pengajar_id,
                    'nama' => $sesiPengajar->first()->pengajar?->name ?? '-',
                    'jumlah_sesi' => $sesiPengajar->count(),
                    'total_menit' => $totalMenit,
                    'fee_pengajar' => $feePengajar,
                ];
            })
            ->sortByDesc('jumlah_sesi')
            ->values();

        return [
            'activeStudentCount' => $activeStudentCount,
            'newStudentCount' => $newStudentCount,
            'rescheduleCount' => $rescheduleCount,
            'feeCompanyTotal' => $feeCompanyTotal,
            'feePengajarTotal' => $feePengajarTotal,
            'perPengajar' => $perPengajar,
        ];
    }
}
