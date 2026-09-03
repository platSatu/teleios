<?php

namespace App\Services\Jadwal;

use App\Models\JadwalBranchSetting;
use App\Models\JadwalKategori;
use App\Models\JadwalKelas;
use App\Models\JadwalRutin;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Generate baris App\Models\JadwalKelas ("Sesi") bertanggal dari SATU
 * App\Models\JadwalRutin, untuk satu bulan target. Logic-nya di-extract
 * dari App\Console\Commands\GenerateJadwalRutinSesi (dipakai command itu
 * tiap tanggal 1 lewat scheduler) supaya bisa DIPANGGIL LANGSUNG juga --
 * dipakai App\Http\Controllers\Jadwal\JadwalStudentController::store()
 * (permintaan user 3 September 2026: begitu Student ditambahkan dengan
 * slot Pengajar yang dicentang, sesi bulan berjalan langsung digenerate
 * saat itu juga, tidak perlu nunggu scheduler bulanan berikutnya).
 *
 * SENGAJA idempotent & aman dipanggil berkali-kali untuk Jadwal Rutin
 * yang sama (unique index jadwal_rutin_id+start_time di jadwal_kelas,
 * lihat createSesi()) -- baik dipanggil dari sini maupun dari command
 * bulanan, hasil akhirnya sama, tidak ada sesi dobel.
 */
class JadwalRutinSesiGenerator
{
    /**
     * Generate sesi untuk SATU Jadwal Rutin, bulan target (default bulan
     * berjalan). Return jumlah sesi baru yang benar-benar dibuat (0 kalau
     * branch belum punya Jam Operasional, Kategori-nya sudah tidak ada,
     * atau semua tanggal bulan itu sudah pernah digenerate sebelumnya).
     */
    public function generateForRutin(JadwalRutin $rutin, ?CarbonImmutable $targetMonth = null): int
    {
        $targetMonth ??= CarbonImmutable::now();

        $branchSetting = $rutin->branchOffice?->jadwalBranchSetting;

        if (! $branchSetting) {
            Log::warning('JadwalRutinSesiGenerator: branch belum punya Jam Operasional, dilewati', [
                'jadwal_rutin_id' => $rutin->id,
                'branch_office_id' => $rutin->branch_office_id,
            ]);

            return 0;
        }

        return $this->generateForRutinAndBranchSetting(
            $rutin,
            $branchSetting,
            $targetMonth->startOfMonth()->startOfDay(),
            $targetMonth->endOfMonth()->endOfDay(),
        );
    }

    /**
     * Sama seperti generateForRutin(), tapi $branchSetting & rentang
     * bulan sudah dihitung di pemanggil -- dipakai
     * App\Console\Commands\GenerateJadwalRutinSesi supaya tidak query
     * ulang branch setting per Jadwal Rutin saat generate banyak baris
     * sekaligus lewat chunkById().
     */
    public function generateForRutinAndBranchSetting(
        JadwalRutin $rutin,
        JadwalBranchSetting $branchSetting,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
    ): int {
        $kategori = $rutin->kategori ?? JadwalKategori::find($rutin->jadwal_kategori_id);

        if (! $kategori) {
            return 0;
        }

        $dates = $this->matchingDates($rutin->hari, $monthStart, $monthEnd);

        // Potong ke rentang efektif Jadwal Rutin ini.
        $efektifMulai = $rutin->efektif_mulai;
        $efektifSelesai = $rutin->efektif_selesai;

        $dates = array_values(array_filter($dates, function (CarbonImmutable $date) use ($efektifMulai, $efektifSelesai) {
            if ($efektifMulai && $date->lt(CarbonImmutable::parse($efektifMulai)->startOfDay())) {
                return false;
            }

            if ($efektifSelesai && $date->gt(CarbonImmutable::parse($efektifSelesai)->endOfDay())) {
                return false;
            }

            return true;
        }));

        // Maks N tanggal pertama per bulan -- tanggal ke-5 (kalau ada)
        // SENGAJA disisakan, tidak pernah digenerate sebagai sesi
        // reguler (lihat App\Console\Commands\GenerateJadwalRutinSesi's
        // class docblock).
        $limit = min($branchSetting->sesi_per_bulan_default, 4);
        $dates = array_slice($dates, 0, $limit);

        $durationMinutes = $rutin->effectiveDurationMinutes();
        $count = 0;

        foreach ($dates as $date) {
            $startTime = $date->setTimeFromTimeString($rutin->jam_mulai);
            $endTime = $startTime->addMinutes($durationMinutes);

            if ($this->createSesi($rutin, $kategori, $branchSetting, $startTime, $endTime, $durationMinutes)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function matchingDates(int $hari, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): array
    {
        $dates = [];
        $cursor = $monthStart;

        while ($cursor->lte($monthEnd)) {
            if ($cursor->dayOfWeek === $hari) {
                $dates[] = $cursor;
            }

            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    /**
     * INSERT langsung + tangkap QueryException kalau unique index
     * (jadwal_rutin_id, start_time) sudah kepakai -- idempotent tanpa
     * perlu SELECT-then-INSERT.
     */
    private function createSesi(JadwalRutin $rutin, JadwalKategori $kategori, JadwalBranchSetting $branchSetting, CarbonImmutable $startTime, CarbonImmutable $endTime, int $durationMinutes): bool
    {
        try {
            JadwalKelas::create([
                'company_id' => $rutin->company_id,
                'branch_office_id' => $rutin->branch_office_id,
                'jadwal_mata_pelajaran_id' => $kategori->jadwal_mata_pelajaran_id,
                'pengajar_id' => $rutin->pengajar_id,
                'student_id' => $rutin->student_id,
                'jadwal_rutin_id' => $rutin->id,
                'jadwal_kategori_id' => $kategori->id,
                'jadwal_ruangan_id' => $rutin->jadwal_ruangan_id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'duration_minutes' => $durationMinutes,
                // Harga BULANAN Kategori dibagi sesi/bulan branch murid ini
                // -- lihat App\Models\JadwalKategori::hargaPerSesi() & migration
                // rename_harga_per_sesi_to_harga_bulanan_on_jadwal_kategori_table.php.
                'harga_sesi' => $kategori->hargaPerSesi($branchSetting->sesi_per_bulan_default),
                'persentase_company' => $kategori->persentase_company,
                'persentase_pengajar' => $kategori->persentase_pengajar,
                'status' => JadwalKelas::STATUS_ACTIVE,
            ]);

            return true;
        } catch (QueryException $e) {
            // Kode 23000 = integrity constraint violation (unique index)
            // -- sesi ini sudah pernah digenerate sebelumnya, aman
            // diabaikan. Error lain (mis. FK rusak) tetap dilempar ulang
            // supaya tidak diam-diam ditelan.
            if ($e->getCode() === '23000') {
                return false;
            }

            throw $e;
        }
    }
}
