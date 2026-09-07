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
     * ID Student AKTIF (distinct) untuk SATU pasangan (pengajar_id,
     * jadwal_kategori_id) -- versi "daftar ID" dari
     * activeMuridCountsForKategoris() di atas (yang cuma menghitung,
     * tidak mengembalikan ID satu-satu), sumber yang sama (JadwalRutin
     * AKTIF), supaya tetap konsisten.
     *
     * Fix 14 September 2026 (laporan user, susulan dari fix badge
     * "Murid" index Pengajar di atas): begitu badge-nya sudah benar
     * menampilkan 1, user klik badge itu -> pindah ke
     * JadwalStudentController::index() (lewat jadwal_mata_pelajaran_id
     * + pengajar_id + jadwal_kategori_id di query string) -- tapi
     * index Student masih memfilter pakai field mentah
     * JadwalStudent.jadwal_mata_pelajaran_id/pengajar_id (SENGAJA tidak
     * pernah pakai jadwal_kategori_id, lihat komentar lama di
     * JadwalStudentController::index()), jadi tetap "Belum ada
     * Student" walau badge-nya sudah bilang 1 -- akar masalahnya SAMA
     * persis dengan yang dijelaskan di docblock
     * activeMuridCountsForKategoris() (field Student bisa basi
     * dibanding JadwalRutin aktualnya). Dipakai
     * JadwalStudentController::index() KHUSUS waktu pengajar_id DAN
     * jadwal_kategori_id dua-duanya ada di query string (datang dari
     * badge/tombol "Add Student" di index Pengajar) -- supaya daftar
     * Student yang tampil SELALU sinkron dengan angka badge yang
     * diklik. Kalau salah satu/kedua parameter itu tidak ada (mis.
     * dibuka dari menu lain), index Student TETAP pakai filter lama
     * (field mentah), tidak diubah -- lihat pemanggilnya.
     *
     * @return Collection<int, string>
     */
    public function activeStudentIdsForPengajarKategori(string $companyId, string $pengajarId, string $kategoriId): Collection
    {
        return JadwalRutin::where('company_id', $companyId)
            ->where('status', JadwalRutin::STATUS_ACTIVE)
            ->where('pengajar_id', $pengajarId)
            ->where('jadwal_kategori_id', $kategoriId)
            ->whereHas('student', fn ($q) => $q->where('status', JadwalStudent::STATUS_ACTIVE))
            ->distinct()
            ->pluck('student_id');
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
     * Nama Mata Pelajaran/Bidang AKTIF milik tiap Student, dikelompokkan
     * per `student_id` -- SAMA POLA dengan activeKategoriNamesByStudent()
     * di atas, dibuat 14 September 2026 (laporan user via screenshot:
     * index Student kolom "Mata Pelajaran / Bidang" menunjukkan "Piano"
     * padahal Jadwal Kelas & index Pengajar sama-sama menunjukkan
     * "Bass" untuk murid yang sama).
     *
     * Kolom "Mata Pelajaran / Bidang" & "Pengajar" di index Student
     * SEBELUMNYA baca field mentah JadwalStudent.jadwal_mata_pelajaran_id
     * / pengajar_id (lihat docblock JadwalStudent -- field ini cuma
     * ke-update kalau baris Student itu SENDIRI diedit lewat form
     * Student, TIDAK ikut ter-update kalau jadwal aktual murid
     * dipindah lewat menu Jadwal Rutin/popup edit Jadwal Kelas) --
     * PERSIS akar masalah yang sama dengan kolom "Kategori" sebelum
     * diperbaiki 4 September 2026 di atas. Sekarang kolom Bidang &
     * Pengajar ikut pola yang sama: di-derive dari JadwalRutin AKTIF,
     * ditampilkan sebagai badge terpisah kalau Student punya lebih
     * dari satu Bidang/Pengajar aktif sekaligus (permintaan user).
     *
     * @param  Collection<int, string>  $studentIds
     * @return Collection<string, Collection<int, string>> keyed by student_id
     */
    public function activeMataPelajaranNamesByStudent(string $companyId, Collection $studentIds): Collection
    {
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return JadwalRutin::where('company_id', $companyId)
            ->whereIn('student_id', $studentIds)
            ->where('status', JadwalRutin::STATUS_ACTIVE)
            ->with('kategori.mataPelajaran:id,name')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('kategori.mataPelajaran.name')->filter()->unique()->values());
    }

    /**
     * Nama Pengajar AKTIF milik tiap Student, dikelompokkan per
     * `student_id` -- SAMA POLA dengan activeMataPelajaranNamesByStudent()
     * tepat di atas, alasan & kronologi lengkap sama, lihat docblock
     * itu.
     *
     * @param  Collection<int, string>  $studentIds
     * @return Collection<string, Collection<int, string>> keyed by student_id
     */
    public function activePengajarNamesByStudent(string $companyId, Collection $studentIds): Collection
    {
        if ($studentIds->isEmpty()) {
            return collect();
        }

        return JadwalRutin::where('company_id', $companyId)
            ->whereIn('student_id', $studentIds)
            ->where('status', JadwalRutin::STATUS_ACTIVE)
            ->with('pengajar:id,name')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('pengajar.name')->filter()->unique()->values());
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
