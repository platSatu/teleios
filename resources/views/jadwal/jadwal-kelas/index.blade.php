@extends('layouts.dashboard')

@section('content')
{{--
    Update 4 September 2026 (permintaan user: "tolong tambahkan fungsi
    export to excel sesuai dengan filter" & "fungsi print sesuai dengan
    filter, pastikan clear area ya dan jenis kertasnya itu landscape").
    CSS cetak di-scope KE HALAMAN INI SAJA (di dalam section "content"
    milik view ini, bukan di layouts.dashboard) -- browser tetap menerapkan <style> di
    mana pun posisinya di halaman, jadi ini TIDAK menyentuh tampilan
    cetak halaman lain sama sekali (permintaan "hati-hati jangan sampai
    kena fungsi lain").

    - `@page { size: landscape }` -- kertas cetak dipaksa landscape,
      sesuai permintaan ("jenis kertasnya itu landscape").
    - Chrome halaman (header/sidebar bawaan layouts.dashboard, judul+
      breadcrumb, form filter, tombol aksi, kolom Aksi tiap baris, tab
      nav Ruangan) disembunyikan pakai kelas `d-print-none` langsung di
      elemennya (utility BAWAAN Bootstrap 5, bukan CSS custom) -- "clear
      area" berarti yang tercetak MURNI tabel jadwalnya, bukan tombol/
      form/navigasi yang tidak relevan di kertas.
    - `.tab-pane` DIPAKSA tampil semua saat cetak (`display:block!important`,
      Bootstrap tab pakai display:none untuk pane non-aktif) -- supaya
      SEMUA tab Ruangan ikut tercetak sekaligus (bukan cuma tab yang
      lagi aktif di layar), masing-masing mulai di halaman kertas baru
      (`break-before: page`) supaya tidak nyambung rapat antar Ruangan.
      Ini best-effort lewat CSS murni (browser print), bukan generate
      PDF di server -- tidak nambah dependency baru.
--}}
<style>
    @media print {
        #appHeader, #sidebar, #sidebar-backdrop, .footer, .main-breadcrumb {
            display: none !important;
        }
        main.app-wrapper, .container-fluid {
            margin: 0 !important;
            padding: 0 !important;
        }
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        .tab-pane {
            display: block !important;
            opacity: 1 !important;
        }
        .tab-pane + .tab-pane {
            break-before: page;
            page-break-before: always;
        }
        @page {
            size: landscape;
            margin: 10mm;
        }
    }
</style>
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success d-print-none">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger d-print-none">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 d-print-none">
                    <div>
                        <h4 class="mb-1">Jadwal Kelas</h4>
                        <p class="text-muted mb-0">Grid jam per Ruangan untuk tanggal terpilih -- halaman ini dipantau terus oleh admin.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        {{--
                            Update 4 September 2026 (permintaan user):
                            Export ke Excel & Cetak, keduanya "sesuai
                            dengan filter" yang lagi aktif -- Export
                            bawa filter lewat query string yang sama
                            persis dipakai halaman ini (lihat
                            JadwalKelasController::resolveFilteredSesi(),
                            SATU sumber dipakai index() & export() supaya
                            dijamin selalu sinkron). Cetak TIDAK perlu
                            request baru -- cukup window.print() atas
                            HTML yang lagi ditampilkan (sudah pasti
                            "sesuai filter" karena itu memang yang lagi
                            di layar), gaya cetaknya diatur CSS di atas.
                        --}}
                        <a href="{{ route('jadwal.kelas.export', array_filter([
                            'date' => $filterDate,
                            'pengajar_id' => $filterPengajarId,
                            'jadwal_mata_pelajaran_id' => $filterMataPelajaranId,
                            'branch_office_id' => $branchOfficeId,
                        ])) }}" class="btn btn-outline-success">
                            <i class="ri-file-excel-2-line"></i> Export ke Excel
                        </a>
                        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                            <i class="ri-printer-line"></i> Cetak
                        </button>
                        <a href="{{ route('jadwal.kelas.create', ['date' => $filterDate, 'pengajar_id' => $filterPengajarId, 'jadwal_mata_pelajaran_id' => $filterMataPelajaranId, 'branch_office_id' => $branchOfficeId]) }}" class="btn btn-primary">
                            <i class="ri-add-line"></i> Tambah Jadwal Kelas
                        </a>
                    </div>
                </div>

                {{--
                    Header KHUSUS cetak -- layar biasa tidak melihat ini
                    (`d-none`), muncul cuma saat window.print() (`d-print-block`,
                    utility bawaan Bootstrap) -- supaya kertas yang
                    dicetak tetap jelas ini laporan Jadwal Kelas tanggal &
                    filter apa, walau judul halaman & form filter di atas
                    disembunyikan (`d-print-none`).
                --}}
                <div class="d-none d-print-block mb-3">
                    <h4 class="mb-1">Jadwal Kelas -- {{ $carbonDate->translatedFormat('l, d F Y') }}</h4>
                    @if($filterBranchName || $filterPengajarName || $filterMataPelajaranName)
                        <p class="mb-0">
                            Filter:
                            @if($filterBranchName) Branch {{ $filterBranchName }} @endif
                            @if($filterPengajarName) &middot; Pengajar {{ $filterPengajarName }} @endif
                            @if($filterMataPelajaranName) &middot; Mata Pelajaran/Bidang {{ $filterMataPelajaranName }} @endif
                        </p>
                    @endif
                </div>

                {{--
                    Update 4 September 2026 (bagian dari redesign grid, lihat
                    docblock JadwalKelasController::index()): `date` SEKARANG
                    SELALU terisi (grid butuh satu sumbu waktu) -- link
                    "Semua Tanggal" dari versi tabel flat sebelumnya DIHAPUS,
                    karena konsepnya tidak berlaku lagi di sini. Filter Branch
                    BARU, cuma tampil kalau company punya lebih dari satu
                    Branch dan admin tidak locked ke satu Branch (lihat
                    ResolvesCompanyContext::isLockedToBranch()).
                --}}
                <form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3 d-print-none">
                    <input type="date" name="date" value="{{ $filterDate }}" class="form-control form-control-sm" style="width: 160px;" onchange="this.form.submit()">
                    @if($branchOffices->isNotEmpty())
                        <select name="branch_office_id" class="form-select form-select-sm" style="width: 200px;" onchange="this.form.submit()">
                            @foreach($branchOffices as $bo)
                                <option value="{{ $bo->id }}" @selected($branchOfficeId === $bo->id)>{{ $bo->name }}</option>
                            @endforeach
                        </select>
                    @endif
                    <select name="pengajar_id" class="form-select form-select-sm" style="width: 200px;" onchange="this.form.submit()">
                        <option value="">Semua Pengajar</option>
                        @foreach($pengajars as $p)
                            <option value="{{ $p->id }}" @selected($filterPengajarId === $p->id)>{{ $p->name }}</option>
                        @endforeach
                    </select>
                    <select name="jadwal_mata_pelajaran_id" class="form-select form-select-sm" style="width: 200px;" onchange="this.form.submit()">
                        <option value="">Semua Bidang / Mata Pelajaran</option>
                        @foreach($mataPelajarans as $mp)
                            <option value="{{ $mp->id }}" @selected($filterMataPelajaranId === $mp->id)>{{ $mp->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="ri-filter-line"></i> Filter</button>
                    @if($filterPengajarId || $filterMataPelajaranId || $filterDate !== now()->toDateString())
                        <a href="{{ route('jadwal.kelas.index') }}" class="btn btn-light btn-sm">Reset Filter</a>
                    @endif
                </form>

                <div class="text-muted small mb-3 d-print-none">
                    <i class="ri-calendar-line"></i> {{ $carbonDate->translatedFormat('l, d F Y') }}
                </div>

                {{--
                    Peringatan kalau grid jam tidak bisa dibangun -- Branch
                    belum punya Jam Operasional, atau tanggal terpilih bukan
                    hari operasional. Sesi yang tetap ada pada tanggal itu
                    TIDAK hilang -- tetap muncul di daftar "Sesi Tanpa Jam"
                    tiap tab (lihat buildRuanganGrid()).
                --}}
                @if(! $branchSetting)
                    <div class="alert alert-warning d-print-none">
                        <i class="ri-error-warning-line"></i> Branch ini belum punya Jam Operasional diatur, jadi grid jam tidak bisa ditampilkan. Sesi yang ada pada tanggal ini tetap ditampilkan di daftar "Sesi Tanpa Jam" tiap tab Ruangan di bawah.
                    </div>
                @elseif(! $isHariOperasional)
                    <div class="alert alert-warning d-print-none">
                        <i class="ri-error-warning-line"></i> {{ $carbonDate->translatedFormat('l, d F Y') }} bukan hari operasional untuk Branch ini, jadi grid jam kosong. Sesi yang ada pada tanggal ini (kalau ada) tetap ditampilkan di daftar "Sesi Tanpa Jam" tiap tab Ruangan di bawah.
                    </div>
                @endif

                @if($ruanganTabs->isEmpty())
                    <div class="alert alert-secondary mb-0">
                        Belum ada Ruangan aktif untuk Branch ini, dan tidak ada Jadwal Kelas pada tanggal ini yang perlu ditampilkan.
                    </div>
                @else
                    <ul class="nav nav-tabs d-print-none" id="ruangan_tabs" role="tablist">
                        @foreach($ruanganTabs as $tab)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="ruangan_tab_{{ $loop->index }}_btn"
                                    data-bs-toggle="tab" data-bs-target="#ruangan_tab_{{ $loop->index }}_pane" type="button" role="tab">
                                    {{ $tab['name'] }}
                                    @if($tab['unmatched']->isNotEmpty())
                                        <span class="badge rounded-pill bg-warning-subtle text-warning ms-1" title="Sesi tanpa jam / di luar jam operasional">{{ $tab['unmatched']->count() }}</span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="tab-content border border-top-0 rounded-bottom-3 p-3">
                        @foreach($ruanganTabs as $tab)
                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="ruangan_tab_{{ $loop->index }}_pane" role="tabpanel">

                                {{--
                                    Judul Ruangan KHUSUS cetak -- saat
                                    dicetak SEMUA tab dipaksa tampil
                                    sekaligus (lihat CSS @media print di
                                    atas), sedangkan nav-tabs yang
                                    biasanya menunjukkan nama Ruangan
                                    disembunyikan -- tanpa ini tiap blok
                                    tercetak tanpa label Ruangan mana.
                                --}}
                                <h5 class="d-none d-print-block mb-2">{{ $tab['name'] }}</h5>

                                @if($tab['unmatched']->isNotEmpty())
                                    <div class="mb-3">
                                        <div class="alert alert-warning py-2 px-3 mb-2 small">
                                            <i class="ri-error-warning-line"></i> Sesi Tanpa Jam / Di Luar Jam Operasional -- jam mulainya kosong, atau di luar rentang jam operasional Branch, sehingga tidak masuk ke grid jam di bawah. Tetap ditampilkan di sini supaya tidak ada yang terlewat.
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm table-centered align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="text-center" style="width: 40px;">No</th>
                                                        <th class="text-center text-nowrap">Pengajar</th>
                                                        <th class="text-center text-nowrap">Bidang</th>
                                                        <th class="text-center text-nowrap">Kategori</th>
                                                        <th class="text-center text-nowrap">Murid</th>
                                                        <th class="text-center text-nowrap">Mulai</th>
                                                        <th class="text-center text-nowrap">Selesai</th>
                                                        <th class="text-center text-nowrap" style="min-width: 340px;">Kehadiran</th>
                                                        <th class="text-center text-nowrap">Status</th>
                                                        <th class="text-center text-nowrap d-print-none">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($tab['unmatched'] as $kelas)
                                                        <tr>
                                                            <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                                            @include('jadwal.jadwal-kelas._sesi-row', ['kelas' => $kelas])
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif

                                @if($tab['rows']->isEmpty())
                                    <div class="alert alert-secondary mb-0 small">Grid jam tidak tersedia untuk tanggal ini (lihat peringatan di atas).</div>
                                @else
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-centered align-middle mb-0" style="min-width: 1100px;">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="text-center" style="width: 40px;">No</th>
                                                    <th class="text-center text-nowrap">Pengajar</th>
                                                    <th class="text-center text-nowrap">Bidang</th>
                                                    <th class="text-center text-nowrap">Kategori</th>
                                                    <th class="text-center text-nowrap">Murid</th>
                                                    <th class="text-center text-nowrap">Mulai</th>
                                                    <th class="text-center text-nowrap">Selesai</th>
                                                    <th class="text-center text-nowrap" style="min-width: 340px;">Kehadiran</th>
                                                    <th class="text-center text-nowrap">Status</th>
                                                    <th class="text-center text-nowrap d-print-none">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($tab['rows'] as $slot)
                                                    @if($slot['istirahat'])
                                                        <tr class="table-danger">
                                                            <td colspan="10" class="text-center fw-semibold">{{ $slot['start'] }} - {{ $slot['end'] }} &mdash; Jam Istirahat</td>
                                                        </tr>
                                                    @elseif($slot['sesi']->isEmpty())
                                                        <tr>
                                                            <td class="text-center text-muted">{{ $loop->iteration }}</td>
                                                            <td class="text-nowrap text-muted">-</td>
                                                            <td class="text-nowrap text-muted">-</td>
                                                            <td class="text-nowrap text-muted">-</td>
                                                            <td class="text-nowrap text-muted">-</td>
                                                            <td class="text-nowrap">{{ $carbonDate->format('d/m/Y') }} {{ $slot['start'] }}</td>
                                                            <td class="text-nowrap">{{ $carbonDate->format('d/m/Y') }} {{ $slot['end'] }}</td>
                                                            <td class="text-muted small">Belum ada sesi</td>
                                                            <td class="text-muted small">-</td>
                                                            <td class="text-end text-nowrap d-print-none">
                                                                <a href="{{ route('jadwal.kelas.create', array_filter([
                                                                    'branch_office_id' => $branchOfficeId,
                                                                    'ruangan_id' => $tab['id'] !== 'tanpa-ruangan' ? $tab['id'] : null,
                                                                    'pengajar_id' => $filterPengajarId,
                                                                    'jadwal_mata_pelajaran_id' => $filterMataPelajaranId,
                                                                    'start_time' => $carbonDate->format('Y-m-d').'T'.$slot['start'],
                                                                    'date' => $filterDate,
                                                                ])) }}" class="btn btn-sm btn-light" title="Tambah sesi di jam ini">
                                                                    <i class="ri-add-line"></i>
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @else
                                                        @foreach($slot['sesi'] as $kelas)
                                                            <tr>
                                                                <td class="text-center text-muted">{{ $loop->parent->iteration }}</td>
                                                                @include('jadwal.jadwal-kelas._sesi-row', ['kelas' => $kelas])
                                                            </tr>
                                                        @endforeach
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{--
    Update 4 September 2026 (permintaan user: "perbaiki fungsi edit nya
    ya dari jadwal kelas, itu masih yang lama, di buat modern popup
    saja. jadi pilih dulu kelas dan category nya, pilih pengajar nya
    baru tampilkan jam pengajarnya berupa tab seperti sblm sblmnya").
    Lihat docblock lengkap App\Http\Controllers\Jadwal\
    JadwalKelasController::editModalData() untuk alasan desainnya.

    SATU modal dipakai ULANG untuk semua baris (bukan satu modal per
    baris seperti pola "rosterModal{id}" di jadwal-mata-pelajaran/
    index.blade.php) -- jumlah baris sesi di halaman ini bisa banyak
    (satu grid bisa berisi puluhan sesi lintas beberapa tab Ruangan,
    SEMUANYA ikut ada di HTML sekaligus gara-gara CSS cetak yang memaksa
    semua tab-pane tampil, lihat <style> di atas) -- kalau tiap baris
    punya modal sendiri dengan salinan penuh dropdown Mata Pelajaran/
    Kategori/Pengajar/Murid/Ruangan, HTML halaman bisa membengkak besar.
    Jadi: SATU modal, opsi dropdown-nya SATU kali render di modal itu
    sendiri, tombol pensil tiap baris cuma bawa data-kelas kecil (nilai
    field kelas ITU SAJA, lihat _sesi-row.blade.php) yang dibaca script
    di bawah untuk mengisi modal saat dibuka.

    <form>-nya SUBMIT BIASA (bukan AJAX/fetch) ke jadwal.kelas.update
    (action di-set dinamis lewat JS saat modal dibuka) -- redirect
    sukses/gagalnya PERSIS sama seperti form Edit lama (lihat
    JadwalKelasController::update()), cuma sekarang kalau validasi
    GAGAL, redirect-nya balik ke index() ini (bukan ke halaman
    jadwal.kelas.edit yang lama) dengan flash `reopenEditKelasId` --
    blok kondisional di bawah yang otomatis buka ulang modal ini + isi
    ulang dari old() supaya isian admin yang gagal disimpan tidak hilang.
--}}
{{--
    Update 7 September 2026 (laporan user + screenshot: "tinggal lbh
    diperpanjang ya soalnya bagian status itu ketutup", lalu SETELAH
    percobaan pertama masih gagal: "kalau di tambahkan scroll , bagaimana
    status dan button save nya tidak kelihatan") -- percobaan PERTAMA
    (cuma max-height + flex-column di `.modal-content`) TERNYATA TIDAK
    CUKUP: akar masalahnya BUKAN cuma soal tinggi, tapi struktur DOM
    modal ini beda dari pola standar Bootstrap yang diasumsikan CSS
    `.modal-dialog-scrollable`/flex-column -- di sini `modal-header`/
    `modal-body`/`modal-footer` DIBUNGKUS satu `<form id="editKelasForm">`
    (perlu SATU form yang membungkus semuanya supaya submit-nya kirim
    SEMUA field sekaligus), jadi mereka BUKAN anak langsung dari
    `.modal-content` -- padahal flex-column Bootstrap/CSS custom di atas
    cuma berlaku untuk ANAK LANGSUNG. Akibatnya `<form>` itu sendiri jadi
    satu-satunya flex item (ukurannya ikut isi, tidak dibatasi), dan di
    DALAM form itu header/body/footer cuma stack biasa TANPA batasan
    tinggi/scroll -- makanya Status+Simpan ke-clip habis (kena
    `overflow:hidden` bawaan `.modal-dialog-scrollable` di `.modal-content`)
    dan TIDAK ADA scrollbar sama sekali, sesuai yang dilaporkan user di
    kedua screenshot.

    Fix: `#editKelasForm` (form pembungkus itu sendiri) ikut dijadikan
    flex column + `flex: 1 1 auto` + `min-height: 0` (kunci flexbox --
    tanpa `min-height: 0` elemen flex tidak akan menyusut di bawah
    tinggi isinya, jadi scroll tidak pernah kepakai) supaya rantai flex
    tersambung SAMPAI ke `modal-body` beneran. `.modal-body` sendiri
    dikasih `flex: 1 1 auto` + `min-height: 0` + `overflow-y: auto` --
    jadi SATU-SATUNYA bagian yang menyusut/scroll, sedangkan
    `modal-header` & `modal-footer` (tombol Batal/Simpan) tetap ukuran
    aslinya & selalu menempel atas/bawah, TIDAK PERNAH ke-clip lagi
    berapa pun panjang isi form-nya. CSS scoped `#editKelasModal` saja
    (TIDAK menyentuh modal lain di aplikasi seperti `rosterModal{id}` di
    jadwal-mata-pelajaran/index.blade.php).
--}}
<style>
    #editKelasModal .modal-dialog {
        max-height: 90vh;
    }
    #editKelasModal .modal-content {
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    #editKelasModal #editKelasForm {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }
    #editKelasModal .modal-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
    }
</style>
<div class="modal fade d-print-none" id="editKelasModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="editKelasForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Jadwal Kelas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div id="editKelasAlert" class="alert alert-danger d-none"></div>

                    <input type="hidden" name="branch_office_id" id="editKelasBranchHidden">

                    {{--
                        Update 7 September 2026 (permintaan user: "triger
                        pertama kali itu pilih pengajar, ketika pengajar
                        itu dipilih baru keluarkan bidang dan category di
                        lanjut dengan jam pelajaran yang available") --
                        urutan field DIBALIK dari versi popup pertama:
                        Pengajar SEKARANG paling atas (isinya SEMUA
                        pengajar yang punya penugasan Kategori aktif,
                        lihat editModalData()), baru Bidang+Kategori
                        (ke-filter ke yang diajarkan pengajar itu), baru
                        panel Jam. Lihat docblock lengkap
                        JadwalKelasController::editModalData().
                    --}}
                    <div class="mb-3">
                        <label class="form-label">Pengajar</label>
                        <select id="editKelasPengajar" name="pengajar_id" class="form-select" required>
                            <option value="">- Pilih Pengajar -</option>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mata Pelajaran / Bidang (opsional)</label>
                            <select id="editKelasMataPelajaran" name="jadwal_mata_pelajaran_id" class="form-select" disabled>
                                <option value="">- Pilih Pengajar dulu -</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Kategori (opsional)</label>
                            <select id="editKelasKategori" name="jadwal_kategori_id" class="form-select" disabled>
                                <option value="">- Pilih Pengajar dulu -</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jam Pengajar</label>
                        <div id="editKelasJamPanel" class="border rounded-3 p-2 bg-light-subtle">
                            <div class="text-muted small">Pilih Pengajar terlebih dahulu.</div>
                        </div>
                        {{--
                            Update 7 September 2026 (permintaan user:
                            "tampilkan seluruh pengajar dan jam pengajar
                            yang available atau yang kosong seperti di
                            student", lalu ditegaskan lagi: "harus nya yg
                            terisi itu tidak bisa diklik") -- badge
                            Kosong/Terisi tiap chip jam (lihat script di
                            bawah, findConflict()) SEKARANG memblokir klik
                            juga -- jam "Terisi" dirender `disabled`
                            (lihat renderJamPanel()), bukan cuma info
                            visual lagi. Teks bantuan di bawah DIPERSINGKAT
                            jadi 1 baris (permintaan user: "catatanya
                            dekat jam pengajar di singkat aja jadi 1
                            baris") -- sebelumnya kepanjangan sampai bikin
                            field Deskripsi di bawah kepotong/terdorong;
                            modal-nya juga diperbesar ke modal-xl (lihat
                            <div class="modal-dialog"> di atas) sebagai
                            langkah tambahan.
                        --}}
                        <div class="form-text">Klik jam untuk isi Tanggal/Waktu otomatis (tetap bisa diedit manual). Jam "Terisi" sudah dipakai sesi lain & tidak bisa dipilih.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" id="editKelasTanggal" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Waktu Mulai</label>
                            <input type="time" id="editKelasJamMulai" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Waktu Selesai</label>
                            <input type="time" id="editKelasJamSelesai" class="form-control">
                        </div>
                    </div>
                    {{-- Hasil gabungan Tanggal + Jam di atas, format yang
                    diterima validator ('date', sama dengan format bawaan
                    input datetime-local yang dipakai form lama) --}}
                    <input type="hidden" name="start_time" id="editKelasStartTime">
                    <input type="hidden" name="end_time" id="editKelasEndTime">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Murid (opsional)</label>
                            <select id="editKelasStudent" name="student_id" class="form-select">
                                <option value="">- Slot Kosong (belum ada murid) -</option>
                                @foreach($students as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }} @if($s->mataPelajaran) — {{ $s->mataPelajaran->name }} @endif @if($s->pengajar) (diajar {{ $s->pengajar->name }}) @endif</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ruangan (opsional)</label>
                            <select id="editKelasRuangan" name="jadwal_ruangan_id" class="form-select">
                                <option value="">- Tanpa Ruangan -</option>
                                @foreach($ruangans as $r)
                                    <option value="{{ $r->id }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Deskripsi (opsional)</label>
                        <textarea id="editKelasDescription" name="description" rows="2" class="form-control"></textarea>
                    </div>

                    <div class="mb-0">
                        <label class="form-label">Status</label>
                        <select id="editKelasStatus" name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Data katalog dipakai popup Edit Jadwal Kelas -- SATU KALI di-embed
    // di sini (bukan diulang per baris), lihat docblock
    // JadwalKelasController::editModalData() & komentar modal di atas.
    window.JADWAL_KELAS_FORM = {
        hariLabels: @json(\App\Models\JadwalRutin::HARI_LABELS),
        allPengajars: @json($allPengajars),
        pengajarBidangMap: @json($pengajarBidangMap),
        pengajarKategoriMap: @json($pengajarKategoriMap),
        pengajarSlotMap: @json($pengajarSlotMap),
        bookedSlots: @json($bookedSlots),
        bookedSlotsDate: @json($bookedSlotsDate),
    };
    @if($errors->any() && session('reopenEditKelasId'))
        // Update 4 September 2026: validasi popup Edit gagal -- lihat
        // komentar JadwalKelasController::update()'s redirect. Data ini
        // dibaca script di bawah untuk membuka ULANG modal yang sama,
        // diisi dari old() (bukan dari data-kelas tombol) supaya isian
        // admin yang tadi gagal disimpan tidak hilang.
        window.JADWAL_KELAS_REOPEN_EDIT = {
            id: @json(session('reopenEditKelasId')),
            update_url: @json(route('jadwal.kelas.update', session('reopenEditKelasId'))),
            branch_office_id: @json(old('branch_office_id')),
            jadwal_mata_pelajaran_id: @json(old('jadwal_mata_pelajaran_id')),
            jadwal_kategori_id: @json(old('jadwal_kategori_id')),
            pengajar_id: @json(old('pengajar_id')),
            student_id: @json(old('student_id')),
            jadwal_ruangan_id: @json(old('jadwal_ruangan_id')),
            start_time: @json(old('start_time')),
            end_time: @json(old('end_time')),
            description: @json(old('description')),
            status: @json(old('status')),
            errors: @json($errors->all()),
        };
    @endif
</script>
<script>
(function () {
    var DATA = window.JADWAL_KELAS_FORM || { hariLabels: {}, allPengajars: [], pengajarBidangMap: {}, pengajarKategoriMap: {}, pengajarSlotMap: {}, bookedSlots: {}, bookedSlotsDate: null };

    var modalEl = document.getElementById('editKelasModal');
    if (!modalEl) {
        return;
    }

    var form = document.getElementById('editKelasForm');
    var branchHidden = document.getElementById('editKelasBranchHidden');
    var mpSelect = document.getElementById('editKelasMataPelajaran');
    var katSelect = document.getElementById('editKelasKategori');
    var pengajarSelect = document.getElementById('editKelasPengajar');
    var jamPanel = document.getElementById('editKelasJamPanel');
    var tanggalInput = document.getElementById('editKelasTanggal');
    var jamMulaiInput = document.getElementById('editKelasJamMulai');
    var jamSelesaiInput = document.getElementById('editKelasJamSelesai');
    var startTimeHidden = document.getElementById('editKelasStartTime');
    var endTimeHidden = document.getElementById('editKelasEndTime');
    var studentSelect = document.getElementById('editKelasStudent');
    var ruanganSelect = document.getElementById('editKelasRuangan');
    var descriptionInput = document.getElementById('editKelasDescription');
    var statusSelect = document.getElementById('editKelasStatus');
    var alertBox = document.getElementById('editKelasAlert');

    var currentSlots = [];
    var currentExcludeKelasId = null;
    var bsModal = null;

    // Isi awal dropdown Pengajar SEKALI di sini (pemicu pertama alur
    // cascading -- lihat komentar Blade di atas #editKelasMataPelajaran)
    // -- daftar SEMUA pengajar yang punya penugasan Kategori aktif,
    // sama persis dengan key yang dipakai pengajarBidangMap/
    // pengajarKategoriMap/pengajarSlotMap/bookedSlots di bawah.
    pengajarSelect.innerHTML = optionsHtml(DATA.allPengajars, '- Pilih Pengajar -', null);

    function escapeHtml(str) {
        return String(str === null || str === undefined ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function optionsHtml(items, placeholder, selectedId) {
        var html = '<option value="">' + placeholder + '</option>';
        (items || []).forEach(function (item) {
            var sel = (selectedId !== null && selectedId !== undefined && selectedId !== '' && String(selectedId) === String(item.id)) ? ' selected' : '';
            html += '<option value="' + item.id + '"' + sel + '>' + escapeHtml(item.name) + '</option>';
        });
        return html;
    }

    // Update 7 September 2026 (permintaan user: urutan pemicu dibalik,
    // lihat docblock JadwalKelasController::editModalData()) -- dua
    // fungsi ini GANTI rebuildKategoriOptions()/rebuildPengajarOptions()
    // versi pertama: sekarang Bidang & Kategori DUA-DUANYA ke-filter
    // dari Pengajar yang dipilih (bukan lagi Pengajar yang ke-filter
    // dari Kategori).
    function rebuildBidangOptions(pengajarId, selectedBidangId) {
        var list = (pengajarId && DATA.pengajarBidangMap && DATA.pengajarBidangMap[pengajarId]) || [];
        mpSelect.innerHTML = optionsHtml(list, pengajarId ? '- Tidak ditentukan -' : '- Pilih Pengajar dulu -', selectedBidangId);
        mpSelect.disabled = !pengajarId;
    }

    function rebuildKategoriOptionsForPengajar(pengajarId, bidangId, selectedKategoriId) {
        var list = (pengajarId && DATA.pengajarKategoriMap && DATA.pengajarKategoriMap[pengajarId]) || [];
        if (bidangId) {
            list = list.filter(function (k) { return String(k.jadwal_mata_pelajaran_id) === String(bidangId); });
        }
        katSelect.innerHTML = optionsHtml(list, pengajarId ? '- Pilih Kategori -' : '- Pilih Pengajar dulu -', selectedKategoriId);
        katSelect.disabled = !pengajarId;
    }

    function slotsFor(pengajarId, kategoriId) {
        if (!pengajarId || !kategoriId || !DATA.pengajarSlotMap || !DATA.pengajarSlotMap[pengajarId]) {
            return [];
        }
        return DATA.pengajarSlotMap[pengajarId][kategoriId] || [];
    }

    // Update 7 September 2026 (permintaan user: "tampilkan ... jam
    // pengajar yang available atau yang kosong seperti di student",
    // lalu: "harus nya yg terisi itu tidak bisa diklik") -- cari
    // App\Models\JadwalKelas LAIN (bukan sesi yang lagi diedit, lihat
    // currentExcludeKelasId) milik pengajar ini yang jam-nya tumpang
    // tindih dengan slot ini, PADA TANGGAL YANG SAMA dengan
    // DATA.bookedSlotsDate (dicek pemanggilnya, lihat renderJamPanel).
    // Hasilnya dipakai renderJamPanel() untuk me-render chip "Terisi"
    // sebagai `disabled` (tidak bisa diklik) -- lihat komentar di sana.
    function findConflict(pengajarId, jamMulai, jamSelesai, excludeKelasId) {
        var bookings = (DATA.bookedSlots && DATA.bookedSlots[pengajarId]) || [];
        for (var i = 0; i < bookings.length; i++) {
            var b = bookings[i];
            if (excludeKelasId && b.kelas_id === excludeKelasId) {
                continue;
            }
            if (jamMulai < b.end && jamSelesai > b.start) {
                return b;
            }
        }
        return null;
    }

    function parseDateLocal(value) {
        var parts = value.split('-');
        return new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
    }

    function formatDateLocal(d) {
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + m + '-' + day;
    }

    function syncHiddenDateTime() {
        var date = tanggalInput.value;
        startTimeHidden.value = (date && jamMulaiInput.value) ? (date + 'T' + jamMulaiInput.value) : '';
        endTimeHidden.value = (date && jamSelesaiInput.value) ? (date + 'T' + jamSelesaiInput.value) : '';
    }

    function applySlotChoice(hari, jamMulai, jamSelesai) {
        // Geser Tanggal MAJU (tidak pernah mundur) ke tanggal terdekat
        // yang hari-nya cocok dengan tab jam yang dipilih -- lihat
        // form-text di bawah panel Jam Pengajar yang menjelaskan ini ke
        // admin. Tidak pernah mundur supaya perilakunya predictable
        // (satu arah saja), bukan "tanggal terdekat bisa maju/mundur"
        // yang lebih susah ditebak admin.
        if (tanggalInput.value) {
            var current = parseDateLocal(tanggalInput.value);
            var diff = (hari - current.getDay() + 7) % 7;
            current.setDate(current.getDate() + diff);
            tanggalInput.value = formatDateLocal(current);
        }
        jamMulaiInput.value = jamMulai;
        jamSelesaiInput.value = jamSelesai;
        syncHiddenDateTime();
    }

    function renderJamPanel(slots, activeHari) {
        currentSlots = slots || [];

        if (!pengajarSelect.value) {
            jamPanel.innerHTML = '<div class="text-muted small">Pilih Pengajar terlebih dahulu.</div>';
            return;
        }
        if (!katSelect.value) {
            jamPanel.innerHTML = '<div class="text-muted small">Pilih Kategori untuk melihat jam ketersediaan Pengajar ini.</div>';
            return;
        }
        if (!currentSlots.length) {
            jamPanel.innerHTML = '<div class="text-muted small">Pengajar ini belum punya jam ketersediaan diisi untuk Kategori ini -- Waktu Mulai/Selesai tetap bisa diisi manual di bawah.</div>';
            return;
        }

        var byHari = {};
        var hariOrder = [];
        currentSlots.forEach(function (s) {
            if (byHari[s.hari] === undefined) {
                byHari[s.hari] = [];
                hariOrder.push(s.hari);
            }
            byHari[s.hari].push(s);
        });
        hariOrder.sort(function (a, b) { return a - b; });

        if (activeHari === undefined || activeHari === null || byHari[activeHari] === undefined) {
            activeHari = hariOrder[0];
        }

        // Update 7 September 2026 -- lihat docblock findConflict() &
        // JadwalKelasController::editModalData(): badge Kosong/Terisi
        // cuma ditampilkan kalau Tanggal di form ini PERSIS sama dengan
        // tanggal grid yang lagi dibuka (DATA.bookedSlotsDate) -- tidak
        // ada data bentrok utk tanggal lain (data di-embed statis, tidak
        // AJAX per ganti tanggal).
        var showTakenInfo = !!tanggalInput.value && tanggalInput.value === DATA.bookedSlotsDate;

        var html = '';

        if (hariOrder.length > 1) {
            html += '<div class="d-flex flex-wrap gap-1 mb-2 js-jam-hari-tabs">';
            hariOrder.forEach(function (hari) {
                html += '<button type="button" class="btn btn-sm ' + (hari === activeHari ? 'btn-secondary' : 'btn-outline-secondary') + ' js-jam-hari-tab" data-hari="' + hari + '">' + (DATA.hariLabels[hari] || '?') + '</button>';
            });
            html += '</div>';
        }

        html += '<div class="d-flex flex-wrap gap-1 js-jam-chip-wrap">';
        byHari[activeHari].forEach(function (s) {
            var conflict = showTakenInfo ? findConflict(pengajarSelect.value, s.jam_mulai, s.jam_selesai, currentExcludeKelasId) : null;
            var badge = showTakenInfo
                ? (conflict
                    ? ' <span class="badge bg-warning-subtle text-warning">Terisi</span>'
                    : ' <span class="badge bg-success-subtle text-success">Kosong</span>')
                : '';
            var titleAttr = conflict
                ? ' title="Sudah dipakai sesi' + (conflict.student_name ? ' murid ' + escapeHtml(conflict.student_name) : ' lain') + ' (' + conflict.start + '-' + conflict.end + '), tidak bisa dipilih"'
                : '';
            // Update 7 September 2026 (permintaan user: "harus nya yg
            // terisi itu tidak bisa diklik") -- chip "Terisi" DIRENDER
            // `disabled` (bukan cuma info visual lagi seperti sebelumnya)
            // supaya benar-benar tidak bisa diklik.
            html += '<button type="button" class="btn btn-sm ' + (conflict ? 'btn-outline-warning' : 'btn-outline-primary') + ' js-jam-chip"' + (conflict ? ' disabled' : '') + titleAttr + ' data-hari="' + s.hari + '" data-jam-mulai="' + s.jam_mulai + '" data-jam-selesai="' + s.jam_selesai + '">' + s.jam_mulai + '-' + s.jam_selesai + badge + '</button>';
        });
        html += '</div>';

        if (!showTakenInfo) {
            html += '<div class="form-text mb-0">Info Kosong/Terisi cuma akurat untuk tanggal ' + escapeHtml(DATA.bookedSlotsDate || '-') + ' (tanggal grid ini).</div>';
        }

        jamPanel.innerHTML = html;

        jamPanel.querySelectorAll('.js-jam-hari-tab').forEach(function (btn) {
            btn.addEventListener('click', function () {
                renderJamPanel(currentSlots, parseInt(btn.getAttribute('data-hari'), 10));
            });
        });
        jamPanel.querySelectorAll('.js-jam-chip').forEach(function (btn) {
            btn.addEventListener('click', function () {
                applySlotChoice(parseInt(btn.getAttribute('data-hari'), 10), btn.getAttribute('data-jam-mulai'), btn.getAttribute('data-jam-selesai'));
            });
        });
    }

    // Update 7 September 2026 (urutan pemicu dibalik, lihat docblock
    // JadwalKelasController::editModalData()) -- Pengajar SEKARANG jadi
    // pemicu paling atas: ganti Pengajar mengosongkan & membangun ulang
    // Bidang+Kategori, ganti Bidang menyaring ulang Kategori, ganti
    // Kategori baru menampilkan panel Jam.
    pengajarSelect.addEventListener('change', function () {
        rebuildBidangOptions(pengajarSelect.value, null);
        rebuildKategoriOptionsForPengajar(pengajarSelect.value, mpSelect.value, null);
        renderJamPanel([]);
    });
    mpSelect.addEventListener('change', function () {
        rebuildKategoriOptionsForPengajar(pengajarSelect.value, mpSelect.value, null);
        renderJamPanel([]);
    });
    katSelect.addEventListener('change', function () {
        var dow = tanggalInput.value ? parseDateLocal(tanggalInput.value).getDay() : undefined;
        renderJamPanel(slotsFor(pengajarSelect.value, katSelect.value), dow);
    });
    tanggalInput.addEventListener('change', function () {
        syncHiddenDateTime();
        renderJamPanel(currentSlots, parseDateLocal(tanggalInput.value).getDay());
    });
    jamMulaiInput.addEventListener('change', syncHiddenDateTime);
    jamSelesaiInput.addEventListener('change', syncHiddenDateTime);

    function fillModal(data) {
        form.action = data.update_url;
        branchHidden.value = data.branch_office_id || '';
        // Update 7 September 2026 -- id sesi yang lagi diedit, dipakai
        // findConflict() supaya jam yang MEMANG sudah dipakai sesi ini
        // sendiri tidak ikut dianggap "Terisi" (bentrok lawan diri
        // sendiri). Tetap sama sepanjang popup terbuka walau admin
        // ganti-ganti Pengajar di dalamnya -- yang berubah cuma isian,
        // sesi yang diedit tetap sesi yang SAMA.
        currentExcludeKelasId = data.id || null;

        pengajarSelect.value = data.pengajar_id || '';
        rebuildBidangOptions(pengajarSelect.value, data.jadwal_mata_pelajaran_id);
        rebuildKategoriOptionsForPengajar(pengajarSelect.value, mpSelect.value, data.jadwal_kategori_id);

        studentSelect.value = data.student_id || '';
        ruanganSelect.value = data.jadwal_ruangan_id || '';
        descriptionInput.value = data.description || '';
        statusSelect.value = data.status || 'active';

        tanggalInput.value = data.date || '';
        jamMulaiInput.value = data.jam_mulai || '';
        jamSelesaiInput.value = data.jam_selesai || '';
        syncHiddenDateTime();

        var dow = data.date ? parseDateLocal(data.date).getDay() : undefined;
        renderJamPanel(slotsFor(pengajarSelect.value, katSelect.value), dow);
    }

    document.querySelectorAll('.js-edit-kelas-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            alertBox.classList.add('d-none');
            alertBox.innerHTML = '';
            fillModal(JSON.parse(btn.getAttribute('data-kelas')));
            // Update 4 September 2026 (bugfix): tombol ini SENGAJA tidak
            // pakai data-bs-toggle="modal" bawaan Bootstrap (biar urutan
            // terjamin -- data harus terisi oleh fillModal() DULU baru
            // modal ditampilkan, supaya admin tidak sempat lihat form
            // kosong sekejap) -- makanya modal-nya WAJIB dibuka manual
            // lewat JS di sini. Baris ini sempat kelewatan sehingga
            // klik pensil tidak terlihat melakukan apa-apa.
            bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        });
    });

    // Buka ulang otomatis kalau baru saja redirect balik ke sini gara-
    // gara validasi popup ini gagal -- lihat window.JADWAL_KELAS_REOPEN_EDIT
    // & komentar JadwalKelasController::update().
    if (window.JADWAL_KELAS_REOPEN_EDIT) {
        var reopen = window.JADWAL_KELAS_REOPEN_EDIT;
        var startParts = (reopen.start_time || '').split('T');

        fillModal({
            id: reopen.id,
            update_url: reopen.update_url,
            branch_office_id: reopen.branch_office_id,
            jadwal_mata_pelajaran_id: reopen.jadwal_mata_pelajaran_id,
            jadwal_kategori_id: reopen.jadwal_kategori_id,
            pengajar_id: reopen.pengajar_id,
            student_id: reopen.student_id,
            jadwal_ruangan_id: reopen.jadwal_ruangan_id,
            date: startParts[0] || '',
            jam_mulai: startParts[1] || '',
            jam_selesai: (reopen.end_time || '').split('T')[1] || '',
            description: reopen.description,
            status: reopen.status,
        });

        if (reopen.errors && reopen.errors.length) {
            alertBox.innerHTML = '<ul class="mb-0">' + reopen.errors.map(function (msg) { return '<li>' + escapeHtml(msg) + '</li>'; }).join('') + '</ul>';
            alertBox.classList.remove('d-none');
        }

        bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        bsModal.show();
    }

    // ---- Backspace guard ----
    // Update 4 September 2026 (permintaan user: "pastikan popup clean
    // dan bekerja dengan baik, terutama ketika fungsi backspace ... ya
    // nama nya juga admin di jaga jaga saja tapi tampilannya tetap user
    // friendly ya"). Sebagian browser/kondisi akan MENAVIGASI KE
    // HALAMAN SEBELUMNYA saat tombol Backspace ditekan waktu fokus
    // BUKAN di kotak isian yang bisa diketik (mis. fokus balik ke badan
    // modal/tombol setelah klik chip jam) -- jebakan umum yang bisa
    // bikin admin tidak sadar KELUAR dari popup ini (kehilangan isian
    // yang belum disimpan) cuma gara-gara pencet Backspace di luar
    // field. Guard ini HANYA aktif SELAGI modal ini terbuka (dipasang
    // saat 'shown.bs.modal', dilepas saat 'hidden.bs.modal') supaya
    // tetap "user friendly" -- tidak mengganggu Backspace di halaman
    // lain atau saat modal ini tertutup.
    function isTypableTarget(el) {
        if (!el) {
            return false;
        }
        var tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
    }

    function backspaceGuard(e) {
        if (e.key === 'Backspace' && !isTypableTarget(e.target)) {
            e.preventDefault();
        }
    }

    modalEl.addEventListener('shown.bs.modal', function () {
        document.addEventListener('keydown', backspaceGuard);
    });
    modalEl.addEventListener('hidden.bs.modal', function () {
        document.removeEventListener('keydown', backspaceGuard);
    });
})();
</script>
@endsection
