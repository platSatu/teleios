<?php

namespace App\Console\Commands;

use App\Models\JadwalRutin;
use App\Services\Jadwal\JadwalRutinSesiGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
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
 * Update 3 September 2026: logic generate-per-baris DIPINDAH ke
 * App\Services\Jadwal\JadwalRutinSesiGenerator supaya bisa dipanggil
 * LANGSUNG juga dari App\Http\Controllers\Jadwal\JadwalStudentController
 * ::store() (begitu Student ditambahkan dengan slot Pengajar dicentang,
 * sesi bulan berjalan langsung digenerate saat itu juga -- tidak perlu
 * nunggu command ini jalan tanggal 1 bulan depan). Command ini sekarang
 * cuma loop Jadwal Rutin aktif + resolve JadwalBranchSetting-nya, lalu
 * delegasikan generate sesungguhnya ke service yang sama.
 */
class GenerateJadwalRutinSesi extends Command
{
    protected $signature = 'jadwal:generate-sesi {--month= : Target bulan format YYYY-MM, default bulan berjalan}';

    protected $description = 'Generate sesi (Jadwal Kelas) bulanan dari Jadwal Rutin aktif';

    public function __construct(private readonly JadwalRutinSesiGenerator $generator)
    {
        parent::__construct();
    }

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

                    $created += $this->generator->generateForRutinAndBranchSetting($rutin, $branchSetting, $monthStart, $monthEnd);
                }
            });

        $this->info("Selesai: {$created} sesi baru digenerate untuk bulan {$monthStart->format('F Y')}."
            .($skippedNoBranchSetting ? " {$skippedNoBranchSetting} Jadwal Rutin dilewati (branch belum punya Jam Operasional)." : ''));

        return self::SUCCESS;
    }
}
