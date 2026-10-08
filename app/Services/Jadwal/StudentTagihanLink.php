<?php

namespace App\Services\Jadwal;

use App\Jobs\SendTagihanLinkWaMessage;
use App\Models\BranchOffice;
use App\Models\Company;
use App\Models\JadwalGrade;
use App\Models\JadwalRutin;
use App\Models\JadwalStudent;
use App\Models\Tagihan;
use App\Models\TagihanCategory;
use App\Models\TagihanCategoryPelanggan;
use App\Models\TagihanPelanggan;
use App\Models\TagihanPenerima;
use App\Services\PackageLimitService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;

/**
 * Jembatan Jadwal -> Tagihan untuk paket Combo (8 Oktober 2026). Harga
 * cukup diisi SEKALI di Grade, Tagihan ikut terisi sendiri:
 *
 *  - syncGrade(): tiap Grade punya SATU Kategori Tagihan per branch
 *    (tagihan_category.jadwal_grade_id + branch_office_id, unik), nama
 *    "Mata Pelajaran · Kategori · Grade", status ikut Grade/Kategori/Mapel.
 *  - syncStudent(): murid aktif = satu TagihanPelanggan miliknya sendiri
 *    yang berlangganan ke Kategori Tagihan tiap Grade di Jadwal Rutin
 *    aktifnya (Grade beda = invoice terpisah). nominal_override dibiarkan
 *    KOSONG, jadi nominal = nominal Tagihan (default harga Grade).
 *    Langganan baru langsung ditambahkan ke Tagihan aktif kategori itu
 *    yang belum jatuh tempo. Murid nonaktif = semua langganannya nonaktif.
 *
 * Hanya berjalan kalau branch punya paket Tagihan (Jadwal sudah pasti,
 * dipanggil dari menu Jadwal). Tagihan & invoice yang sudah dibuat TIDAK
 * PERNAH diubah. Langganan ke Kategori Tagihan manual (bukan dari Grade)
 * tidak disentuh, kecuali saat murid dinonaktifkan (perilaku lama).
 * Semua method idempotent -- aman dipanggil berkali-kali; pemanggil
 * menjalankannya di dalam DB::transaction yang sama dengan perubahannya.
 */
class StudentTagihanLink
{
    /** @var array<string, bool> branch_office_id => punya paket Tagihan */
    private array $tagihanBranches = [];

    public function __construct(private PackageLimitService $packages)
    {
    }

    /**
     * Total harga bulanan Grade dari Jadwal Rutin AKTIF tiap murid
     * (Grade yang sama dihitung sekali) -- dipakai Laporan Jadwal (Omset).
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
     * Grade dibuat/diubah (atau Kategori/Mata Pelajaran induknya):
     * perbarui nama & status semua Kategori Tagihan milik Grade ini, dan
     * pastikan ada satu untuk branch Mata Pelajaran-nya (kalau ada).
     */
    public function syncGrade(JadwalGrade $grade): void
    {
        $grade->loadMissing(['kategori.mataPelajaran', 'company']);

        foreach (TagihanCategory::where('jadwal_grade_id', $grade->id)->get() as $category) {
            $category->update($this->gradeAttributes($grade));
        }

        $branchId = $grade->kategori?->mataPelajaran?->branch_office_id;

        if ($branchId && $grade->company && $this->branchHasTagihan($grade->company, $branchId)) {
            $this->categoryFor($grade, $branchId);
        }
    }

    /** @param  iterable<JadwalGrade>  $grades */
    public function syncGrades(iterable $grades): void
    {
        foreach ($grades as $grade) {
            $this->syncGrade($grade);
        }
    }

    /**
     * Sebelum Grade/Kategori/Mata Pelajaran dihapus: Kategori Tagihan
     * miliknya dinonaktifkan (tidak dihapus -- invoice-nya riwayat uang).
     * Setelah Grade terhapus, kolom jadwal_grade_id-nya jadi NULL (FK).
     *
     * @param  Collection<int, string>|array<int, string>  $gradeIds
     */
    public function retireGrades(Collection|array $gradeIds): void
    {
        TagihanCategory::whereIn('jadwal_grade_id', collect($gradeIds)->all())
            ->update(['status' => 'inactive']);
    }

    /**
     * Samakan langganan Tagihan murid dengan Jadwal Rutin aktifnya.
     * Dipanggil setelah Student/Jadwal Rutin disimpan atau dinonaktifkan,
     * dan sebelum Student dihapus ($removing = true).
     */
    public function syncStudent(JadwalStudent $student, bool $removing = false): void
    {
        $student = JadwalStudent::whereKey($student->id)->lockForUpdate()->first();

        if (! $student) {
            return;
        }

        $pelanggan = $student->tagihan_pelanggan_id
            ? TagihanPelanggan::where('company_id', $student->company_id)->find($student->tagihan_pelanggan_id)
            : null;

        if ($removing || $student->status !== JadwalStudent::STATUS_ACTIVE) {
            if ($pelanggan) {
                TagihanCategoryPelanggan::where('tagihan_pelanggan_id', $pelanggan->id)->update(['status' => 'inactive']);
            }

            return;
        }

        $branchId = $student->branch_office_id;

        // Murid pindah branch: langganan Grade di pelanggan lama dihentikan,
        // nanti dibuatkan pelanggan baru di branch barunya.
        if ($pelanggan && $pelanggan->branch_office_id !== $branchId) {
            $this->stopGradeSubscriptions($pelanggan->id, []);
            $pelanggan = null;
        }

        if (! $branchId || ! $student->company || ! $this->branchHasTagihan($student->company, $branchId)) {
            return;
        }

        $gradeIds = JadwalRutin::where('company_id', $student->company_id)
            ->where('student_id', $student->id)
            ->where('status', JadwalRutin::STATUS_ACTIVE)
            ->whereNotNull('jadwal_grade_id')
            ->distinct()
            ->pluck('jadwal_grade_id');

        if ($gradeIds->isEmpty() && ! $pelanggan) {
            return;
        }

        $data = [
            'name' => $student->name,
            'phone_number' => $student->parent_phone_number ?: $student->student_phone_number,
            'status' => 'active',
        ];

        if ($pelanggan) {
            $pelanggan->update($data);
        } else {
            $pelanggan = TagihanPelanggan::create($data + [
                'company_id' => $student->company_id,
                'branch_office_id' => $branchId,
                'kirim_link_otomatis' => false,
            ]);
            $student->update(['tagihan_pelanggan_id' => $pelanggan->id]);
        }

        $categoryIds = [];

        $grades = JadwalGrade::where('company_id', $student->company_id)
            ->whereIn('id', $gradeIds)
            ->with('kategori.mataPelajaran')
            ->get();

        foreach ($grades as $grade) {
            $category = $this->categoryFor($grade, $branchId);
            $categoryIds[] = $category->id;
            $this->subscribe($category, $pelanggan);
        }

        $this->stopGradeSubscriptions($pelanggan->id, $categoryIds);
    }

    /** Kategori Tagihan milik Grade ini di satu branch (dibuat kalau belum ada). */
    private function categoryFor(JadwalGrade $grade, string $branchId): TagihanCategory
    {
        $existing = TagihanCategory::where('jadwal_grade_id', $grade->id)->where('branch_office_id', $branchId)->first();

        if ($existing) {
            return $existing;
        }

        try {
            return TagihanCategory::create($this->gradeAttributes($grade) + [
                'company_id' => $grade->company_id,
                'branch_office_id' => $branchId,
                'jadwal_grade_id' => $grade->id,
            ]);
        } catch (QueryException $e) {
            // Dibuat bersamaan oleh request lain (unique grade + branch).
            if ($e->getCode() === '23000') {
                return TagihanCategory::where('jadwal_grade_id', $grade->id)->where('branch_office_id', $branchId)->firstOrFail();
            }

            throw $e;
        }
    }

    private function gradeAttributes(JadwalGrade $grade): array
    {
        $kategori = $grade->kategori;
        $mapel = $kategori?->mataPelajaran;

        $active = $grade->status === 'active'
            && ($kategori?->status ?? 'active') === 'active'
            && ($mapel?->status ?? 'active') === 'active';

        return [
            'name' => mb_substr(implode(' · ', array_filter([$mapel?->name, $kategori?->name, $grade->name])), 0, 255),
            'status' => $active ? 'active' : 'inactive',
        ];
    }

    private function subscribe(TagihanCategory $category, TagihanPelanggan $pelanggan): void
    {
        $langganan = TagihanCategoryPelanggan::firstOrNew([
            'tagihan_category_id' => $category->id,
            'tagihan_pelanggan_id' => $pelanggan->id,
        ]);

        if ($langganan->exists && $langganan->status === 'active') {
            return;
        }

        $langganan->status = 'active';
        $langganan->save();

        // Langganan baru: ikut ditagih di Tagihan aktif kategori ini yang
        // belum jatuh tempo (aturan nominal sama dengan Tambah Penerima).
        $openTagihan = Tagihan::where('tagihan_category_id', $category->id)
            ->where('status', Tagihan::STATUS_ACTIVE)
            ->whereDate('due_date', '>=', now()->toDateString())
            ->get();

        foreach ($openTagihan as $tagihan) {
            if ($tagihan->penerima()->where('tagihan_pelanggan_id', $pelanggan->id)->exists()) {
                continue;
            }

            $penerima = TagihanPenerima::create([
                'tagihan_id' => $tagihan->id,
                'tagihan_pelanggan_id' => $pelanggan->id,
                'company_id' => $tagihan->company_id,
                'branch_office_id' => $tagihan->branch_office_id,
                'amount' => $langganan->nominal_override ?? $tagihan->amount,
                'status' => TagihanPenerima::STATUS_BELUM_BAYAR,
            ]);

            if ($pelanggan->kirim_link_otomatis) {
                SendTagihanLinkWaMessage::dispatch($penerima->id)->afterCommit();
            }
        }
    }

    /** Nonaktifkan langganan ke Kategori Tagihan Grade yang tidak lagi diambil murid. */
    private function stopGradeSubscriptions(string $pelangganId, array $keepCategoryIds): void
    {
        TagihanCategoryPelanggan::where('tagihan_pelanggan_id', $pelangganId)
            ->where('status', 'active')
            ->whereNotIn('tagihan_category_id', $keepCategoryIds)
            ->whereIn('tagihan_category_id', TagihanCategory::whereNotNull('jadwal_grade_id')->select('id'))
            ->update(['status' => 'inactive']);
    }

    private function branchHasTagihan(Company $company, string $branchId): bool
    {
        if (! array_key_exists($branchId, $this->tagihanBranches)) {
            $branch = BranchOffice::where('company_id', $company->id)->find($branchId);

            $this->tagihanBranches[$branchId] = $branch
                && $this->packages->hasActiveCategoryPackage($company, ['Tagihan', 'Pembayaran'], $branch);
        }

        return $this->tagihanBranches[$branchId];
    }
}
