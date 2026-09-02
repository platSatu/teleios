<?php

namespace App\Services\Jadwal;

use App\Models\JadwalKelas;
use App\Models\JadwalKelasRescheduleRequest;
use App\Models\JadwalStudent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data untuk Laporan Jadwal v2 (CLAUDE.md item #15 spec poin 12 & 13)
 * -- SATU menu dengan filter rentang tanggal bebas (dari-sampai; satu
 * hari kalau dari==sampai, satu bulan kalau dari=awal bulan sampai
 * akhir bulan, atau rentang lain), dipakai bersama oleh
 * App\Http\Controllers\Jadwal\JadwalLaporanController (tampilan
 * halaman) dan App\Exports\Jadwal\JadwalLaporanExport (download
 * Excel), supaya angka yang tampil di layar dan yang di-export SELALU
 * sama persis (satu sumber query).
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
     * Semua sesi aktif dalam rentang tanggal $from-$to (spec poin 12
     * -- "kelas mana aja yang terpakai, daftar murid nya, dan jam
     * nya"). $from/$to sebaiknya sudah startOfDay()/endOfDay() dari
     * pemanggil supaya rentang tanggalnya inklusif penuh.
     *
     * @return Collection<int, JadwalKelas>
     */
    public function sesiUntukRentang(string $companyId, ?string $branchOfficeId, Carbon $from, Carbon $to): Collection
    {
        return JadwalKelas::where('company_id', $companyId)
            ->where('status', JadwalKelas::STATUS_ACTIVE)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->whereBetween('start_time', [$from, $to])
            ->with(['mataPelajaran:id,name', 'kategori:id,name', 'ruangan:id,name', 'pengajar:id,name', 'student:id,name'])
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Rekap untuk rentang tanggal $from-$to (spec poin 13): jumlah
     * murid aktif/baru, jumlah reschedule, total fee (company vs
     * pengajar), lama mengajar per pengajar.
     *
     * @return array{
     *   activeStudentCount: int, newStudentCount: int, rescheduleCount: int,
     *   feeCompanyTotal: float, feePengajarTotal: float,
     *   perPengajar: Collection<int, array{pengajar_id: string, nama: string, jumlah_sesi: int, total_menit: int, fee_pengajar: float}>,
     * }
     */
    public function rekap(string $companyId, ?string $branchOfficeId, Carbon $from, Carbon $to): array
    {
        $activeStudentCount = JadwalStudent::where('company_id', $companyId)
            ->where('status', JadwalStudent::STATUS_ACTIVE)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->count();

        $newStudentCount = JadwalStudent::where('company_id', $companyId)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $rescheduleCount = JadwalKelasRescheduleRequest::where('company_id', $companyId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $paidSesi = JadwalKelas::where('company_id', $companyId)
            ->where('status', JadwalKelas::STATUS_ACTIVE)
            ->when($branchOfficeId, fn ($q) => $q->where('branch_office_id', $branchOfficeId))
            ->whereBetween('start_time', [$from, $to])
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
