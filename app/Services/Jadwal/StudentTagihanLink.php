<?php

namespace App\Services\Jadwal;

use App\Models\JadwalGrade;
use App\Models\JadwalRutin;
use App\Models\JadwalStudent;
use App\Models\TagihanCategory;
use App\Models\TagihanCategoryPelanggan;
use App\Models\TagihanPelanggan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Jembatan Student -> Tagihan (7 Oktober 2026, tombol "Daftarkan Tagihan"
 * di index Student). Modul Tagihan tetap berdiri sendiri (dipakai juga untuk
 * pelanggan umum); di sini Student cukup:
 *  1. dibuatkan/ditautkan ke SATU TagihanPelanggan miliknya sendiri
 *     (jadwal_student.tagihan_pelanggan_id, cabang selalu = cabang Student);
 *  2. dilanggankan ke satu TagihanCategory cabang itu dengan nominal_override
 *     = harga bulanan Grade yang diambil (paket tiap murid bisa berbeda).
 * Tagihan bulanannya tetap dibuat dari menu Tagihan (alur yang sudah ada,
 * nominal_override otomatis dipakai per penerima).
 */
class StudentTagihanLink
{
    /**
     * Total harga bulanan Grade dari Jadwal Rutin AKTIF tiap murid
     * (Grade yang sama dihitung sekali).
     *
     * @return array<string, int> student_id => nominal
     */
    public function monthlyAmounts(string $companyId, Collection $studentIds): array
    {
        if ($studentIds->isEmpty()) {
            return [];
        }

        $pairs = JadwalRutin::where('company_id', $companyId)
            ->whereIn('student_id', $studentIds)
            ->where('status', JadwalRutin::STATUS_ACTIVE)
            ->whereNotNull('jadwal_grade_id')
            ->get(['student_id', 'jadwal_grade_id'])
            ->unique(fn ($r) => $r->student_id.'|'.$r->jadwal_grade_id);

        $prices = JadwalGrade::whereIn('id', $pairs->pluck('jadwal_grade_id')->unique())
            ->pluck('harga_bulanan', 'id');

        return $pairs->groupBy('student_id')
            ->map(fn ($rows) => (int) round($rows->sum(fn ($r) => (float) ($prices[$r->jadwal_grade_id] ?? 0))))
            ->all();
    }

    /**
     * Daftarkan / perbarui langganan tagihan murid. Satu transaksi + lock
     * baris Student supaya klik ganda tidak membuat 2 pelanggan.
     */
    public function register(JadwalStudent $student, TagihanCategory $category, int $amount, bool $kirimLink): TagihanCategoryPelanggan
    {
        return DB::transaction(function () use ($student, $category, $amount, $kirimLink) {
            $locked = JadwalStudent::whereKey($student->id)->lockForUpdate()->firstOrFail();

            $pelanggan = $locked->tagihan_pelanggan_id
                ? TagihanPelanggan::where('company_id', $locked->company_id)
                    ->where('branch_office_id', $locked->branch_office_id)
                    ->find($locked->tagihan_pelanggan_id)
                : null;

            $data = [
                'name' => $locked->name,
                'phone_number' => $locked->parent_phone_number ?: $locked->student_phone_number,
                'status' => 'active',
                'kirim_link_otomatis' => $kirimLink,
            ];

            if ($pelanggan) {
                $pelanggan->update($data);
            } else {
                $pelanggan = TagihanPelanggan::create($data + [
                    'company_id' => $locked->company_id,
                    'branch_office_id' => $locked->branch_office_id,
                ]);
                $locked->update(['tagihan_pelanggan_id' => $pelanggan->id]);
            }

            return TagihanCategoryPelanggan::updateOrCreate(
                ['tagihan_category_id' => $category->id, 'tagihan_pelanggan_id' => $pelanggan->id],
                ['nominal_override' => $amount, 'status' => 'active'],
            );
        });
    }

    /** Murid dinonaktifkan: semua langganan tagihannya ikut nonaktif (histori tetap). */
    public function deactivate(JadwalStudent $student): void
    {
        if ($student->tagihan_pelanggan_id) {
            TagihanCategoryPelanggan::where('tagihan_pelanggan_id', $student->tagihan_pelanggan_id)
                ->update(['status' => 'inactive']);
        }
    }
}
