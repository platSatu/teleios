<?php

namespace App\Services\Jadwal;

use App\Models\JadwalPengajarKategori;
use App\Models\JadwalRutin;
use App\Models\JadwalStudent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Sumber TUNGGAL untuk angka/badge "siapa yang aktif sekarang" yang
 * dipakai lintas menu Jadwal (Pengajar, Mata Pelajaran/Bidang, Student)
 * — dibuat 5 September 2026 atas permintaan user setelah laporan "menu
 * pengajar data murid masih 0 seharusnya ada 1" + "kode makin gemuk,
 * tiap perbaikan bikin function baru di controller masing-masing".
 *
 * SEBELUM class ini ada, 3 controller berbeda masing-masing punya query
 * SENDIRI untuk pertanyaan yang secara konsep sama ("berapa murid aktif
 * pengajar ini di bidang ini", dst) -- ditulis terpisah di waktu
 * berbeda (lihat riwayat CLAUDE.md item #15), gampang salah satu
 * ke-update sementara yang lain lupa, itulah akar drift yang berulang
 * kali dilaporkan user. Aturan main mulai sekarang: kalau ada menu lain
 * yang butuh angka "murid/pengajar/ruangan aktif", TAMBAH METHOD DI
 * SINI dan panggil dari situ -- JANGAN tulis query serupa lagi di
 * controller manapun.
 *
 * Semua method di sini SENGAJA cuma menghitung dari data yang
 * `status = active` (definisi "aktif sekarang", konsisten di semua
 * method) -- riwayat/data nonaktif/terhapus TIDAK pernah ikut kehitung
 * di sini. Kalau suatu menu butuh angka historis (mis. total sesi
 * sepanjang masa), itu TETAP pakai query terpisah di controllernya
 * (lihat `withCount('kelas')` di JadwalMataPelajaranController::index()
 * — sengaja tidak dipindah ke sini, beda konsep).
 */
class JadwalCountsService
{
    /**
     * Jumlah murid AKTIF per pasangan (pengajar_id, jadwal_mata_pelajaran_id).
     * Dipindah dari JadwalPengajarController::attachMuridCounts() apa
     * adanya, PLUS satu perbaikan konsistensi: sekarang ikut filter
     * `status = active` (sebelumnya TIDAK difilter sama sekali --
     * murid nonaktif ikut kehitung di sini padahal badge sejenis di
     * Mata Pelajaran sudah lebih dulu difilter aktif, lihat commit
     * sebelumnya "Perbaiki relasi data Pengajar-Student..." — sekarang
     * disamakan supaya definisi "murid aktif" konsisten di semua menu).
     *
     * @param  Collection<int, array{pengajar_id: string, jadwal_mata_pelajaran_id: string}>  $pairs  unik, tidak boleh berisi null
     * @return Collection<string, object{pengajar_id: string, jadwal_mata_pelajaran_id: string, total: int}> keyed "{pengajar_id}|{jadwal_mata_pelajaran_id}"
     */
    public function activeMuridCountsForPairs(string $companyId, Collection $pairs): Collection
    {
        if ($pairs->isEmpty()) {
            return collect();
        }

        return JadwalStudent::where('company_id', $companyId)
            ->where('status', JadwalStudent::STATUS_ACTIVE)
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $p) {
                    $q->orWhere(function ($qq) use ($p) {
                        $qq->where('pengajar_id', $p['pengajar_id'])
                            ->where('jadwal_mata_pelajaran_id', $p['jadwal_mata_pelajaran_id']);
                    });
                }
            })
            ->selectRaw('pengajar_id, jadwal_mata_pelajaran_id, count(*) as total')
            ->groupBy('pengajar_id', 'jadwal_mata_pelajaran_id')
            ->get()
            ->keyBy(fn ($row) => $row->pengajar_id.'|'.$row->jadwal_mata_pelajaran_id);
    }

    /**
     * Nama Kategori AKTIF milik tiap Student, dikelompokkan per
     * `student_id` -- dipindah apa adanya dari
     * JadwalStudentController::index() (query sudah benar sejak awal,
     * cuma dipindahkan ke sini supaya satu tempat dengan method lain di
     * class ini). Lihat docblock JadwalStudent (`jadwal_kategori_id`
     * TIDAK disimpan langsung di tabel itu) untuk alasan kenapa ini
     * di-derive dari JadwalRutin, bukan kolom langsung.
     *
     * @param  Collection<int, string>  $studentIds
     * @return Collection<string, Collection<int, string>> keyed by student_id
     */
    public function activeKategoriNamesByStudent(string $companyId, Collection $studentIds): Collection
    {
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return JadwalRutin::where('company_id', $companyId)
            ->whereIn('student_id', $studentIds)
            ->where('status', JadwalRutin::STATUS_ACTIVE)
            ->with('kategori:id,name')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('kategori.name')->filter()->unique()->values());
    }

    /**
     * Correlated subquery Builder: jumlah Pengajar AKTIF (distinct)
     * yang ditugaskan ke Kategori manapun di bawah SATU Mata Pelajaran
     * -- dipakai lewat `addSelect()` di query utama (butuh `Builder`
     * mentah, bukan hasil dieksekusi, supaya `whereColumn()` bisa
     * merujuk baris Mata Pelajaran di query luar). Dipindah apa adanya
     * dari JadwalMataPelajaranController::index().
     */
    public function pengajarCountSubquery(): Builder
    {
        return JadwalPengajarKategori::query()
            ->selectRaw('count(distinct jadwal_pengajar_kategori.pengajar_id)')
            ->join('jadwal_kategori', 'jadwal_kategori.id', '=', 'jadwal_pengajar_kategori.jadwal_kategori_id')
            ->whereColumn('jadwal_kategori.jadwal_mata_pelajaran_id', 'jadwal_mata_pelajaran.id')
            ->where('jadwal_pengajar_kategori.status', JadwalPengajarKategori::STATUS_ACTIVE);
    }

    /**
     * Correlated subquery Builder: jumlah Student AKTIF milik SATU Mata
     * Pelajaran. Sama pola dengan pengajarCountSubquery() di atas.
     */
    public function studentCountSubquery(): Builder
    {
        return JadwalStudent::selectRaw('count(*)')
            ->whereColumn('jadwal_student.jadwal_mata_pelajaran_id', 'jadwal_mata_pelajaran.id')
            ->where('jadwal_student.status', JadwalStudent::STATUS_ACTIVE);
    }

    /**
     * Correlated subquery Builder: jumlah Ruangan (distinct) yang
     * SEDANG dipakai Jadwal Rutin aktif di bawah SATU Mata Pelajaran.
     * Sama pola dengan pengajarCountSubquery() di atas.
     */
    public function ruanganCountSubquery(): Builder
    {
        return JadwalRutin::query()
            ->selectRaw('count(distinct jadwal_rutin.jadwal_ruangan_id)')
            ->join('jadwal_kategori', 'jadwal_kategori.id', '=', 'jadwal_rutin.jadwal_kategori_id')
            ->whereColumn('jadwal_kategori.jadwal_mata_pelajaran_id', 'jadwal_mata_pelajaran.id')
            ->where('jadwal_rutin.status', JadwalRutin::STATUS_ACTIVE)
            ->whereNotNull('jadwal_rutin.jadwal_ruangan_id');
    }
}
