{{--
    Update 4 September 2026 (permintaan user: "tampilannya jam pengajar
    itu di ganti dengan tab"): panel checklist ketersediaan Pengajar
    (dikelompokkan per Kategori, lihat App\Http\Controllers\Jadwal\
    JadwalStudentController::create()/pengajarSlotsPanel()) SEBELUMNYA
    ditumpuk vertikal (satu checklist penuh di bawah checklist lain) --
    kalau Pengajar ngajar banyak Kategori, halamannya jadi panjang.
    Sekarang jadi Bootstrap nav-tabs, satu tab per Kategori -- label tab
    itu sendiri jadi cara "tampilkan Kategori di form" (permintaan yang
    sama, satu fitur). Partial ini SATU sumber dipakai create.blade.php
    DAN edit.blade.php (sama seperti _slot-checklist.blade.php).

    Variabel yang diharapkan:
    - $pengajarKategoris: Collection App\Models\JadwalPengajarKategori,
      tiap baris sudah dapat properti tambahan `slots` (hasil slotsFor())
      & `branchSettingMissing` (bool) -- lihat pemanggil.
    - $tabIdPrefix: string unik per halaman (mis. "create"/"edit")
      supaya id elemen HTML tab tidak bentrok kalau partial ini dipakai
      lebih dari sekali di halaman yang sama (tidak terjadi saat ini,
      tapi jaga-jaga).

    Kalau cuma SATU Kategori, tab-nya di-skip (langsung tampilkan
    checklist-nya saja) -- chrome tab untuk satu pilihan cuma nambah
    klik tanpa manfaat.

    Tab yang mengandung slot `mine` (Edit Student -- jadwal aktif murid
    ini) dapat badge hijau berisi jumlahnya, supaya admin langsung tahu
    tab mana yang punya jadwal aktif tanpa harus buka satu-satu.

    Tab yang AKTIF secara default: kalau baru submit gagal validasi
    ($errors->any()) DAN salah satu Kategori punya slot yang tadi
    disubmit (old()), tab Kategori itu yang dibuka duluan (supaya
    centangan yang baru diubah admin tidak "hilang dari pandangan" --
    datanya sendiri tetap aman lewat old(), ini murni supaya kelihatan).
    Selain itu, tab pertama yang aktif.
--}}
@php
    $tabItems = $pengajarKategoris->values();
@endphp

@if($tabItems->count() <= 1)
    @foreach($tabItems as $pk)
        @if($pk->branchSettingMissing)
            <div class="alert alert-secondary mb-0 py-2">Branch Kategori ini belum punya Jam Operasional diatur, jadi slot tidak bisa ditampilkan -- atur dulu lewat menu Jadwal &gt; Branch &gt; Jam Operasional.</div>
        @else
            @include('jadwal.jadwal-student._slot-checklist', [
                'slots' => $pk->slots,
                'fieldName' => 'jadwal_rutin_slot_ids['.$pk->jadwal_kategori_id.'][]',
                'oldKey' => 'jadwal_rutin_slot_ids.'.$pk->jadwal_kategori_id,
                'idPrefix' => $tabIdPrefix.'_slot_'.$pk->id,
            ])
        @endif
    @endforeach
@else
    @php
        $activeIndex = 0;
        if ($errors->any()) {
            foreach ($tabItems as $idx => $pk) {
                if (! empty(old('jadwal_rutin_slot_ids.'.$pk->jadwal_kategori_id, []))) {
                    $activeIndex = $idx;
                    break;
                }
            }
        }
    @endphp
    <ul class="nav nav-tabs" id="{{ $tabIdPrefix }}_kategori_tabs" role="tablist">
        @foreach($tabItems as $i => $pk)
            @php
                $mineCount = $pk->slots->where('mine', true)->count();
                $tabButtonId = $tabIdPrefix.'_kattab_btn_'.$pk->id;
                $tabPaneId = $tabIdPrefix.'_kattab_pane_'.$pk->id;
            @endphp
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $i === $activeIndex ? 'active' : '' }}" id="{{ $tabButtonId }}"
                    data-bs-toggle="tab" data-bs-target="#{{ $tabPaneId }}" type="button" role="tab">
                    {{ $pk->kategori->name ?? '-' }}
                    @if($mineCount > 0)
                        <span class="badge rounded-pill bg-success-subtle text-success ms-1">{{ $mineCount }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom-3 p-3">
        @foreach($tabItems as $i => $pk)
            @php $tabPaneId = $tabIdPrefix.'_kattab_pane_'.$pk->id; @endphp
            <div class="tab-pane fade {{ $i === $activeIndex ? 'show active' : '' }}" id="{{ $tabPaneId }}" role="tabpanel">
                @if($pk->branchSettingMissing)
                    <div class="alert alert-secondary mb-0 py-2">Branch Kategori ini belum punya Jam Operasional diatur, jadi slot tidak bisa ditampilkan -- atur dulu lewat menu Jadwal &gt; Branch &gt; Jam Operasional.</div>
                @else
                    @include('jadwal.jadwal-student._slot-checklist', [
                        'slots' => $pk->slots,
                        'fieldName' => 'jadwal_rutin_slot_ids['.$pk->jadwal_kategori_id.'][]',
                        'oldKey' => 'jadwal_rutin_slot_ids.'.$pk->jadwal_kategori_id,
                        'idPrefix' => $tabIdPrefix.'_slot_'.$pk->id,
                    ])
                @endif
            </div>
        @endforeach
    </div>
@endif
