@if($kategori)
    {{-- Terkunci -- datang dari drill-down "+ Add Pengajar" di index
             Kategori (jadwal_kategori_id ada & valid di query string).
             Pola sama seperti "ina" project's University Album Photo
             create(): input disabled + hidden field terpisah supaya
             tetap terkirim walau elemen disabled. --}}
    <div class="mb-3">
        <label class="form-label">Kategori</label>
        <input type="text" class="form-control" value="{{ $kategori->name }}" disabled>
    </div>
    <input type="hidden" name="jadwal_kategori_id" value="{{ $kategori->id }}">
@else
    {{-- Bebas -- mode global (menu sidebar "Pengajar" langsung) atau
             edit() yang SELALU dropdown bebas (lihat class docblock
             App\Http\Controllers\Jadwal\JadwalPengajarController). --}}
    <div class="mb-3">
        <label class="form-label">Kategori</label>
        <select name="jadwal_kategori_id" class="form-select @error('jadwal_kategori_id') is-invalid @enderror" required>
            <option value="">- Pilih Kategori -</option>
            @foreach ($kategoris as $k)
                <option value="{{ $k->id }}" @selected(old('jadwal_kategori_id', $pengajarKategori->jadwal_kategori_id ?? '') == $k->id)>
                    {{ $k->name }}@if($k->mataPelajaran) ({{ $k->mataPelajaran->name }})@endif
                </option>
            @endforeach
        </select>
        @error('jadwal_kategori_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endif

<div class="mb-3">
    <label class="form-label">Pengajar</label>
    <select name="pengajar_id" class="form-select @error('pengajar_id') is-invalid @enderror" required>
        <option value="">- Pilih Pengajar -</option>
        @foreach ($teamMembers as $member)
            <option value="{{ $member->id }}" @selected(old('pengajar_id', $pengajarKategori->pengajar_id ?? '') == $member->id)>{{ $member->name }}</option>
        @endforeach
    </select>
    @error('pengajar_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">
        @if($kategori)
            Anggota tim company (Team Members) yang bisa dijadikan pengajar untuk Kategori "{{ $kategori->name }}".
        @else
            Anggota tim company (Team Members) yang bisa dijadikan pengajar.
        @endif
    </div>
</div>

@php
    // Ketersediaan sekarang berupa BANYAK slot (hari + rentang jam),
    // bukan checkbox hari + satu jam yang berlaku ke semua hari --
    // kasus lapangan: pengajar bisa Senin 10:00-12:00 LALU 17:00-19:00
    // di hari yang sama, dan tiap hari boleh beda-beda. Lihat
    // App\Models\JadwalPengajarJadwal & migration
    // create_jadwal_pengajar_kategori_jadwal_table.php.
    $existingJadwal = old('jadwal');
    if (! $existingJadwal) {
        $existingJadwal = ($pengajarKategori ?? null)?->jadwals
            ? $pengajarKategori->jadwals->map(fn ($j) => [
                'hari' => $j->hari,
                'jam_mulai' => substr($j->jam_mulai, 0, 5),
                'jam_selesai' => substr($j->jam_selesai, 0, 5),
            ])->all()
            : [];
    }
    if (empty($existingJadwal)) {
        $existingJadwal = [['hari' => '', 'jam_mulai' => '', 'jam_selesai' => '']];
    }
@endphp
<div class="mb-3">
    <label class="form-label d-block">Hari &amp; Jam Tersedia</label>
    <div class="form-text mb-2">
        Satu baris = satu hari + satu rentang jam. Kalau pengajar bisa mengajar di hari yang sama tapi jam yang berbeda
        (mis. Senin 10:00-12:00 lalu 17:00-19:00), klik "+ Tambah Baris" dan pilih Senin lagi dengan jam yang lain.
    </div>

    <div id="jadwal-rows">
        @foreach($existingJadwal as $i => $row)
            <div class="row g-2 align-items-start mb-2 jadwal-row">
                <div class="col-5 col-md-4">
                    <select name="jadwal[{{ $i }}][hari]" class="form-select form-select-sm @error('jadwal.'.$i.'.hari') is-invalid @enderror">
                        <option value="">- Hari -</option>
                        @foreach(\App\Models\JadwalRutin::HARI_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(($row['hari'] ?? '') !== '' && (int) $row['hari'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('jadwal.'.$i.'.hari')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-3 col-md-3">
                    <input type="time" name="jadwal[{{ $i }}][jam_mulai]" class="form-control form-control-sm @error('jadwal.'.$i.'.jam_mulai') is-invalid @enderror" value="{{ $row['jam_mulai'] ?? '' }}">
                    @error('jadwal.'.$i.'.jam_mulai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-3 col-md-3">
                    <input type="time" name="jadwal[{{ $i }}][jam_selesai]" class="form-control form-control-sm @error('jadwal.'.$i.'.jam_selesai') is-invalid @enderror" value="{{ $row['jam_selesai'] ?? '' }}">
                    @error('jadwal.'.$i.'.jam_selesai')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-1 col-md-2">
                    <button type="button" class="btn btn-sm btn-outline-danger jadwal-row-remove" title="Hapus baris">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </div>
            </div>
        @endforeach
    </div>
    <button type="button" id="jadwal-row-add" class="btn btn-sm btn-light mt-1">
        <i class="ri-add-line"></i> Tambah Baris
    </button>
    @error('jadwal')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $pengajarKategori->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $pengajarKategori->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<script>
// "Tambah Baris" hari & jam ketersediaan pengajar -- kenyamanan sisi
// klien saja (bisa tambah/hapus baris bebas), validasi sebenarnya
// tetap di server (App\Http\Controllers\Jadwal\JadwalPengajarController
// ::validator()). Index baris yang dipakai di name="jadwal[i][...]"
// boleh bolong (misal 0, 2, 5 setelah baris lain dihapus) -- PHP tetap
// membacanya sebagai array biasa di sisi server.
(function () {
    var container = document.getElementById('jadwal-rows');
    var addBtn = document.getElementById('jadwal-row-add');
    if (!container || !addBtn) return;

    var counter = {{ max(count($existingJadwal), 1) }};
    var hariOptions = @json(\App\Models\JadwalRutin::HARI_LABELS);

    function buildRow(index) {
        var wrap = document.createElement('div');
        wrap.className = 'row g-2 align-items-start mb-2 jadwal-row';

        var hariHtml = '<option value="">- Hari -</option>';
        Object.keys(hariOptions).forEach(function (value) {
            hariHtml += '<option value="' + value + '">' + hariOptions[value] + '</option>';
        });

        wrap.innerHTML =
            '<div class="col-5 col-md-4"><select name="jadwal[' + index + '][hari]" class="form-select form-select-sm">' + hariHtml + '</select></div>' +
            '<div class="col-3 col-md-3"><input type="time" name="jadwal[' + index + '][jam_mulai]" class="form-control form-control-sm"></div>' +
            '<div class="col-3 col-md-3"><input type="time" name="jadwal[' + index + '][jam_selesai]" class="form-control form-control-sm"></div>' +
            '<div class="col-1 col-md-2"><button type="button" class="btn btn-sm btn-outline-danger jadwal-row-remove" title="Hapus baris"><i class="ri-delete-bin-line"></i></button></div>';

        return wrap;
    }

    addBtn.addEventListener('click', function () {
        container.appendChild(buildRow(counter));
        counter++;
    });

    container.addEventListener('click', function (e) {
        var btn = e.target.closest('.jadwal-row-remove');
        if (!btn) return;

        var rows = container.querySelectorAll('.jadwal-row');
        if (rows.length <= 1) {
            // Jangan biarkan 0 baris -- kosongkan isi baris terakhir saja.
            var row = btn.closest('.jadwal-row');
            row.querySelectorAll('select, input').forEach(function (el) { el.value = ''; });
            return;
        }
        btn.closest('.jadwal-row').remove();
    });
})();
</script>
