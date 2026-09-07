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
     * Jumlah murid AKTIF (distinct) per pasangan (pengajar_id,
     * jadwal_kategori_id), dihitung dari App\Models\JadwalRutin AKTIF.
     *
     * Update 14 September 2026 (laporan user via screenshot: index
     * Pengajar menampilkan "Murid: 0" untuk Stevany N/Kategori Jazz,
     * padahal index Student DAN grid Jadwal Kelas sama-sama menunjukkan
     * murid "Vallery Jocelyn Nathania" aktif di kombinasi itu) --
     * method ini MENGGANTIKAN activeMuridCountsForPairs() versi lama
     * (dihapus, satu-satunya pemanggilnya cuma
     * JadwalPengajarController::attachMuridCounts()) yang menghitung
     * lewat pasangan (pengajar_id, jadwal_mata_pelajaran_id) dicocokkan
     * ke App\Models\JadwalStudent.jadwal_mata_pelajaran_id/pengajar_id
     * -- BUKAN akar masalahnya, cuma gejalanya: Jadwal Rutin/Jadwal
     * Kelas seorang murid BISA dipindah ke Pengajar/Kategori lain lewat
     * menu Jadwal Rutin atau popup Edit Jadwal Kelas TANPA menyentuh
     * baris JadwalStudent-nya sama sekali (reconciliation basi di
     * JadwalStudentController::update() cuma jalan kalau admin
     * mengedit Student ITU SENDIRI) -- field
     * jadwal_mata_pelajaran_id/pengajar_id di baris Student jadi bisa
     * "basi" dibanding jadwal aktualnya. activeKategoriNamesByStudent()
     * di bawah (dipakai badge Kategori index Student) dan grid Jadwal
     * Kelas SAMA-SAMA sudah baca langsung dari JadwalRutin/JadwalKelas
     * -- badge "Murid" index Pengajar WAJIB pakai sumber yang sama
     * (jadwal_kategori_id, bukan jadwal_mata_pelajaran_id) supaya
     * ketiga menu selalu match, tidak peduli data Student-nya sendiri
     * basi atau tidak. Ini TIDAK memperbaiki akar drift-nya (baris
     * Student yang basi tetap basi) -- kalau itu juga mau
     * direkonsiliasi otomatis tiap Jadwal Rutin/Jadwal Kelas diedit di
     * luar form Student, itu perubahan terpisah yang lebih besar.
     *
     * @param  Collection<int, array{pengajar_id: string, jadwal_kategori_id: string}>  $pairs  unik, tidak boleh berisi null
     * @return Collection<string, int> keyed "{pengajar_id}|{jadwal_kategori_id}"
     */
    public function activeMuridCountsForKategoris(string $companyId, Collection $pairs): Collection
    {
        if ($pairs->isEmpty()) {
            return collect();
        }

        return JadwalRutin::where('company_id', $companyId)
            ->where('status', JadwalRutin::STATUS_ACTIVE)
            ->whereHas('student', fn ($q) => $q->where('status', JadwalStudent::STATUS_ACTIVE))
            ->where(function ($q) use ($pairs) {
                foreach ($pairs as $p) {
                    $q->orWhere(function ($qq) use ($p) {
                        $qq->where('pengajar_id', $p['pengajar_id'])
                            ->where('jadwal_kategori_id', $p['jadwal_kategori_id']);
                    });
                }
            })
            ->selectRaw('pengajar_id, jadwal_kategori_id, count(distinct student_id) as total')
            ->groupBy('pengajar_id', 'jadwal_kategori_id')
            ->get()
            ->keyBy(fn ($row) => $row->pengajar_id.'|'.$row->jadwal_kategori_id)
            ->map(fn ($row) => (int) $row->total);
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
