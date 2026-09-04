{{--
    Checklist slot ketersediaan Pengajar -- dipakai create.blade.php
    (checklist tunggal, satu Kategori dari konteks drill-down) DAN
    edit.blade.php (dipanggil berulang, satu kali per Kategori Pengajar,
    lihat docblock App\Http\Controllers\Jadwal\JadwalStudentController::
    pengajarSlotsPanel()). Satu file sumber supaya dua tempat itu tidak
    baku beda perilaku.

    Update 4 September 2026 (permintaan user): slot yang taken => true
    (sudah kepakai murid lain, lihat docblock slotsFor()) TETAP
    ditampilkan di sini -- checkbox-nya disabled + dicoret, BUKAN
    dihilangkan dari daftar seperti sebelumnya, supaya admin tahu jam
    itu memang ditawarkan pengajar tapi sudah terisi.

    Update 4 September 2026 (bug fix, permintaan user -- "jadwal yg
    dipilih saat pertamaa kali create tidak keluar ketika ingin
    melakukan edit"): slot yang mine => true (sudah jadi Jadwal Rutin
    AKTIF milik murid yang SEDANG di-edit sendiri, lihat docblock
    slotsFor()) tampil TERCENTANG + badge hijau "jadwal aktif murid
    ini", supaya kelihatan di Edit Student tanpa admin harus centang
    ulang manual.

    Update 4 September 2026 (revisi lagi, permintaan user -- "gimana
    kalau murid itu pengen ganti jadwal nya kalau di disable... ceklist
    hanya penanda saja... berlaku untuk murid lain [saja]"): slot
    `mine` TIDAK di-disable (beda dari draft sebelumnya) -- checkbox-nya
    tetap BISA di-uncheck, sama seperti form edit pada umumnya. Yang
    disable HANYA slot `taken` (dipakai murid LAIN). Meng-uncheck slot
    `mine` lalu Simpan Perubahan akan MENGHAPUS baris Jadwal Rutin
    itu (lihat App\Http\Controllers\Jadwal\JadwalStudentController::
    update(), reconciliation-nya dihitung ulang dari server, bukan
    percaya submitted array) -- murid itu sendiri TIDAK terpengaruh
    (tetap berstatus sama), cuma satu baris jadwal mingguannya yang
    hilang. Checked-state default (checklist pertama kali dibuka vs
    setelah validasi gagal & redisplay) dibedakan lewat `$errors->any()`
    -- kalau TIDAK ada error (buka pertama kali / reload ganti
    Pengajar), default checked = status `mine` apa adanya dari
    database; kalau ADA error (redisplay setelah submit gagal),
    default checked = persis apa yang tadi disubmit lewat `old()`
    (supaya centangan admin yang baru saja diubah tidak "reset" balik
    ke `mine` versi lama waktu re-render).

    Update 4 September 2026 (permintaan user, dari screenshot Edit
    Student -- daftar Senin+Jumat ditumpuk jadi satu list panjang):
    slot sekarang dikelompokkan per HARI juga, ditampilkan sebagai tab
    (satu tab per hari) kalau Pengajar-nya tersedia di LEBIH DARI SATU
    hari -- pola yang sama dengan _kategori-tabs.blade.php (tab per
    Kategori), cuma level-nya di bawahnya (per hari, di dalam SATU
    Kategori). Kalau cuma SATU hari, tab-nya di-skip (langsung datar,
    sama seperti sebelumnya). Baris checkbox-nya sendiri di-extract ke
    _slot-checkbox.blade.php supaya dipakai ULANG di kedua mode (tidak
    lagi menyertakan nama hari di label-nya -- nama hari sekarang cuma
    ditulis SEKALI, di judul mode datar / label tab).

    Variabel yang diharapkan:
    - $slots: Collection hasil slotsFor().
    - $fieldName: nama field checkbox, mis. "jadwal_rutin_slot_ids[]"
      (create, satu Kategori) atau "jadwal_rutin_slot_ids[{id}][]" (edit,
      per Kategori).
    - $oldKey: key untuk old(), mis. "jadwal_rutin_slot_ids" atau
      "jadwal_rutin_slot_ids.{id}" (dot notation, dibaca Laravel dari
      array bersarang).
    - $idPrefix: prefix atribut HTML id supaya unik kalau partial ini
      dipanggil berulang di halaman yang sama (edit.blade.php, satu kali
      per Kategori) -- juga dipakai sebagai basis id tab hari di sini.
--}}
@php
    // Lihat blok komentar di atas -- $errors->any() dipakai membedakan
    // "buka pertama kali" (default checked = `mine`) vs "redisplay
    // setelah submit gagal" (default checked = persis old() yang tadi
    // disubmit, termasuk kalau admin baru saja meng-uncheck slot mine).
    $isResubmit = $errors->any();
    $slotsByHari = $slots->groupBy('hari')->sortKeys();

    // Tab hari yang aktif secara default: kalau redisplay setelah gagal
    // validasi, hari yang mengandung slot yang TADI disubmit; kalau
    // tidak, hari yang mengandung slot `mine` (jadwal aktif murid ini);
    // fallback hari pertama. Sama semangatnya dengan $activeIndex di
    // _kategori-tabs.blade.php.
    $activeHari = $slotsByHari->keys()->first();
    if ($isResubmit) {
        $oldChecked = (array) old($oldKey, []);
        foreach ($slotsByHari as $hari => $hariSlots) {
            if ($hariSlots->pluck('id')->intersect($oldChecked)->isNotEmpty()) {
                $activeHari = $hari;
                break;
            }
        }
    } else {
        foreach ($slotsByHari as $hari => $hariSlots) {
            if ($hariSlots->where('mine', true)->isNotEmpty()) {
                $activeHari = $hari;
                break;
            }
        }
    }
@endphp
@if($slots->isEmpty())
    <div class="alert alert-secondary mb-0">Pengajar ini belum punya jam ketersediaan diisi untuk Kategori ini.</div>
@elseif($slotsByHari->count() <= 1)
    @php $hariLabel = $slots->first()['hari_label'] ?? null; @endphp
    @if($hariLabel)
        <div class="fw-semibold small text-muted mb-1">{{ $hariLabel }}</div>
    @endif
    <div class="d-flex flex-column gap-2">
        @foreach($slots as $slot)
            @include('jadwal.jadwal-student._slot-checkbox', ['slot' => $slot, 'fieldName' => $fieldName, 'oldKey' => $oldKey, 'idPrefix' => $idPrefix, 'isResubmit' => $isResubmit])
        @endforeach
    </div>
@else
    <ul class="nav nav-tabs" id="{{ $idPrefix }}_hari_tabs" role="tablist">
        @foreach($slotsByHari as $hari => $hariSlots)
            @php
                $hariMineCount = $hariSlots->where('mine', true)->count();
                $hariTabId = $idPrefix.'_hari_'.$hari;
            @endphp
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $hari === $activeHari ? 'active' : '' }}" id="{{ $hariTabId }}_btn"
                    data-bs-toggle="tab" data-bs-target="#{{ $hariTabId }}_pane" type="button" role="tab">
                    {{ $hariSlots->first()['hari_label'] }}
                    @if($hariMineCount > 0)
                        <span class="badge rounded-pill bg-success-subtle text-success ms-1">{{ $hariMineCount }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom-3 p-2">
        @foreach($slotsByHari as $hari => $hariSlots)
            @php $hariTabId = $idPrefix.'_hari_'.$hari; @endphp
            <div class="tab-pane fade {{ $hari === $activeHari ? 'show active' : '' }}" id="{{ $hariTabId }}_pane" role="tabpanel">
                <div class="d-flex flex-column gap-2">
                    @foreach($hariSlots as $slot)
                        @include('jadwal.jadwal-student._slot-checkbox', ['slot' => $slot, 'fieldName' => $fieldName, 'oldKey' => $oldKey, 'idPrefix' => $idPrefix, 'isResubmit' => $isResubmit])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
