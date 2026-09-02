<?php

namespace App\Console\Commands;

use App\Models\JadwalBranchSetting;
use App\Models\JadwalKategori;
use App\Models\JadwalKelas;
use App\Models\JadwalRutin;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * Auto-generate baris App\Models\JadwalKelas ("Sesi") bertanggal dari
 * setiap App\Models\JadwalRutin aktif -- Jadwal v2, CLAUDE.md item #15
 * spec poin 6. Dijadwalkan bulanan lewat bootstrap/app.php's
 * ->withSchedule() (tanggal 1 tiap bulan), tapi SENGAJA idempotent &
 * aman dijalankan ulang kapan saja (retry manual, atau admin generate
 * bulan depan lebih awal) -- lihat migration
 * add_unique_index_rutin_start_time_to_jadwal_kelas_table.php.
 *
 * Untuk tiap Jadwal Rutin: ambil semua tanggal di bulan target yang
 * hari-nya cocok (Carbon::dayOfWeek), potong ke rentang efektif_mulai/
 * efektif_selesai, lalu ambil paling banyak N tanggal pertama (N =
 * JadwalBranchSetting::sesi_per_bulan_default, default 4) -- kalau hari
 * itu jatuh 5x dalam sebulan, tanggal ke-5 SENGAJA TIDAK digenerate
 * (disisakan buat sesi pengganti, lihat spec poin 8 & App\Models\
 * JadwalKelas::pengganti_dari_sesi_id).
 *
 * Setiap sesi yang digenerate mencatat SNAPSHOT harga/persentase/
 * durasi/kategori/ruangan dari Kategori pada saat generate (lihat
 * migration add_jadwal_v2_columns_to_jadwal_kelas_table.php's docblock
 * untuk kenapa snapshot).
 */
class GenerateJadwalRutinSesi extends Command
{
    protected $signature = 'jadwal:generate-sesi {--month= : Target bulan format YYYY-MM, default bulan berjalan}';

    protected $description = 'Generate sesi (Jadwal Kelas) bulanan dari Jadwal Rutin aktif';

    public function handle(): int
    {
        $monthOption = $this->option('month');

        try {
            $targetMonth = $monthOption
                ? CarbonImmutable::createFromFormat('Y-m-d', $monthOption.'-01')
                : CarbonImmutable::now()->startOfMonth();
        } catch (\Throwable) {
            $this->error('Format --month harus YYYY-MM, mis. 2026-10.');

            return self::FAILURE;
        }

        $monthStart = $targetMonth->startOfMonth()->startOfDay();
        $monthEnd = $targetMonth->endOfMonth()->endOfDay();

        $created = 0;
        $skippedNoBranchSetting = 0;

        JadwalRutin::query()
            ->where('status', JadwalRutin::STATUS_ACTIVE)
            ->whereDate('efektif_mulai', '<=', $monthEnd)
            ->where(function ($q) use ($monthStart) {
                $q->whereNull('efektif_selesai')->orWhereDate('efektif_selesai', '>=', $monthStart);
            })
            ->with(['kategori', 'branchOffice.jadwalBranchSetting'])
            ->chunkById(100, function ($rutins) use (&$created, &$skippedNoBranchSetting, $monthStart, $monthEnd) {
                foreach ($rutins as $rutin) {
                    $branchSetting = $rutin->branchOffice?->jadwalBranchSetting;

                    if (! $branchSetting) {
                        $skippedNoBranchSetting++;

                        Log::warning('GenerateJadwalRutinSesi: branch belum punya Jam Operasional, dilewati', [
                            'jadwal_rutin_id' => $rutin->id,
                            'branch_office_id' => $rutin->branch_office_id,
                        ]);

                        continue;
                    }

                    $created += $this->generateForRutin($rutin, $branchSetting, $monthStart, $monthEnd);
                }
            });

        $this->info("Selesai: {$created} sesi baru digenerate untuk bulan {$monthStart->format('F Y')}."
            .($skippedNoBranchSetting ? " {$skippedNoBranchSetting} Jadwal Rutin dilewati (branch belum punya Jam Operasional)." : ''));

        return self::SUCCESS;
    }

    private function generateForRutin(JadwalRutin $rutin, JadwalBranchSetting $branchSetting, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): int
    {
        $kategori = $rutin->kategori;

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
        // reguler (lihat class docblock).
        $limit = min($branchSetting->sesi_per_bulan_default, 4);
        $dates = array_slice($dates, 0, $limit);

        $durationMinutes = $rutin->effectiveDurationMinutes();
        $count = 0;

        foreach ($dates as $date) {
            $startTime = $date->setTimeFromTimeString($rutin->jam_mulai);
            $endTime = $startTime->addMinutes($durationMinutes);

            if ($this->createSesi($rutin, $kategori, $startTime, $endTime, $durationMinutes)) {
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
     * perlu SELECT-then-INSERT (lihat migration's docblock & class
     * docblock file ini).
     */
    private function createSesi(JadwalRutin $rutin, JadwalKategori $kategori, CarbonImmutable $startTime, CarbonImmutable $endTime, int $durationMinutes): bool
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
                'harga_sesi' => $kategori->harga_per_sesi,
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
