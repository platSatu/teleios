<?php

namespace App\Console\Commands;

use App\Models\JadwalGrade;
use App\Models\JadwalStudent;
use App\Models\TagihanCategory;
use App\Models\TagihanCategoryPelanggan;
use App\Services\Jadwal\StudentTagihanLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sekali jalan untuk data lama (paket Combo, 8 Oktober 2026): buatkan
 * Kategori Tagihan tiap Grade & langganan murid aktif sesuai Jadwal
 * Rutin-nya -- logika sama persis dengan yang berjalan otomatis saat
 * Grade/Student disimpan (App\Services\Jadwal\StudentTagihanLink).
 * Aman diulang. --dry-run: semua dijalankan lalu di-rollback, hanya
 * menampilkan angka.
 */
class SyncJadwalTagihan extends Command
{
    protected $signature = 'tagihan:sync-jadwal {--dry-run : Tampilkan hasil tanpa menyimpan}';

    protected $description = 'Hubungkan Grade & Student (menu Jadwal) ke Kategori Tagihan dan langganannya';

    public function handle(StudentTagihanLink $link): int
    {
        $before = $this->counts();

        DB::beginTransaction();

        try {
            $grades = 0;
            JadwalGrade::with(['kategori.mataPelajaran', 'company'])->chunkById(200, function ($rows) use ($link, &$grades) {
                $link->syncGrades($rows);
                $grades += $rows->count();
            });

            $students = 0;
            JadwalStudent::where('status', JadwalStudent::STATUS_ACTIVE)->chunkById(200, function ($rows) use ($link, &$students) {
                foreach ($rows as $student) {
                    $link->syncStudent($student);
                    $students++;
                }
            });

            $after = $this->counts();
            $manual = $this->studentsWithManualSubscriptions();

            $this->option('dry-run') ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->info(($this->option('dry-run') ? '[DRY RUN, tidak disimpan] ' : '')."Grade diperiksa: {$grades}, murid aktif diperiksa: {$students}");
        $this->table(['', 'Sebelum', 'Sesudah'], [
            ['Kategori Tagihan dari Grade', $before['categories'], $after['categories']],
            ['Langganan aktif ke kategori Grade', $before['subscriptions'], $after['subscriptions']],
        ]);

        if ($manual->isNotEmpty()) {
            $this->warn('Murid berikut JUGA masih berlangganan Kategori Tagihan manual (cek supaya tidak ditagih dobel):');
            $this->table(['Murid', 'Kategori manual'], $manual->all());
        }

        return self::SUCCESS;
    }

    private function counts(): array
    {
        return [
            'categories' => TagihanCategory::whereNotNull('jadwal_grade_id')->count(),
            'subscriptions' => TagihanCategoryPelanggan::where('status', 'active')
                ->whereIn('tagihan_category_id', TagihanCategory::whereNotNull('jadwal_grade_id')->select('id'))
                ->count(),
        ];
    }

    private function studentsWithManualSubscriptions()
    {
        return DB::table('jadwal_student as s')
            ->join('tagihan_category_pelanggan as cp', 'cp.tagihan_pelanggan_id', '=', 's.tagihan_pelanggan_id')
            ->join('tagihan_category as c', 'c.id', '=', 'cp.tagihan_category_id')
            ->where('s.status', JadwalStudent::STATUS_ACTIVE)
            ->where('cp.status', 'active')
            ->whereNull('c.jadwal_grade_id')
            ->orderBy('s.name')
            ->get(['s.name as murid', 'c.name as kategori'])
            ->map(fn ($r) => [$r->murid, $r->kategori]);
    }
}
