{{--
    Update 4 September 2026: satu baris checkbox slot ketersediaan
    Pengajar -- di-extract dari _slot-checklist.blade.php supaya bisa
    dipakai ULANG di DUA mode render (datar utk satu hari, tab per hari
    utk lebih dari satu hari, lihat _slot-checklist.blade.php). Label
    SENGAJA tidak menyertakan nama hari lagi (beda dari sebelumnya) --
    nama hari sekarang ditampilkan SEKALI di judul/tab, bukan diulang di
    tiap baris.

    Variabel yang diharapkan (semua diteruskan apa adanya dari
    _slot-checklist.blade.php):
    - $slot: satu item dari Collection hasil slotsFor().
    - $fieldName, $oldKey, $idPrefix: lihat _slot-checklist.blade.php.
    - $isResubmit: bool, dihitung SEKALI di pemanggil (bukan di sini)
      supaya konsisten dalam satu render.
--}}
<div class="form-check">
    <input type="checkbox" name="{{ $fieldName }}" value="{{ $slot['id'] }}"
        id="{{ $idPrefix }}_{{ $slot['id'] }}" class="form-check-input"
        @disabled($slot['taken'])
        @checked($isResubmit
            ? (! $slot['taken'] && in_array($slot['id'], old($oldKey, [])))
            : (! $slot['taken'] && ($slot['mine'] ?? false)))>
    <label for="{{ $idPrefix }}_{{ $slot['id'] }}" class="form-check-label {{ $slot['taken'] ? 'text-muted text-decoration-line-through' : '' }}">
        {{ $slot['jam_mulai'] }} - {{ $slot['jam_selesai'] }}
        @if($slot['taken'])
            <span class="badge bg-secondary-subtle text-secondary fw-normal text-decoration-none ms-1">sudah dipakai{{ $slot['taken_by'] ? ' — '.$slot['taken_by'] : '' }}</span>
        @elseif($slot['mine'] ?? false)
            <span class="badge bg-success-subtle text-success fw-normal text-decoration-none ms-1">jadwal aktif murid ini</span>
        @endif
    </label>
</div>
