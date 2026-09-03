<input type="hidden" name="jadwal_kategori_id" value="{{ $kategori->id }}">

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
    <div class="form-text">Anggota tim company (Team Members) yang bisa dijadikan pengajar untuk Kategori "{{ $kategori->name }}".</div>
</div>

@php
    $hariBisa = old('hari_bisa', $pengajarKategori->hari_bisa ?? []);
@endphp
<div class="mb-3">
    <label class="form-label d-block">Hari yang Bisa</label>
    <div class="d-flex flex-wrap gap-3">
        @foreach(\App\Models\JadwalRutin::HARI_LABELS as $value => $label)
            <div class="form-check">
                <input type="checkbox" name="hari_bisa[]" value="{{ $value }}" id="hari_bisa_{{ $value }}"
                    class="form-check-input" @checked(in_array($value, $hariBisa))>
                <label for="hari_bisa_{{ $value }}" class="form-check-label">{{ $label }}</label>
            </div>
        @endforeach
    </div>
    @error('hari_bisa')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Jam Mulai</label>
        <input type="time" name="jam_mulai" class="form-control @error('jam_mulai') is-invalid @enderror"
            value="{{ old('jam_mulai', ($pengajarKategori ?? null)?->jam_mulai ? substr($pengajarKategori->jam_mulai, 0, 5) : '') }}" required>
        @error('jam_mulai')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Jam Selesai</label>
        <input type="time" name="jam_selesai" class="form-control @error('jam_selesai') is-invalid @enderror"
            value="{{ old('jam_selesai', ($pengajarKategori ?? null)?->jam_selesai ? substr($pengajarKategori->jam_selesai, 0, 5) : '') }}" required>
        @error('jam_selesai')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
<div class="form-text mb-3">Rentang jam pengajar ini bisa mengajar Kategori "{{ $kategori->name }}", berlaku di semua hari yang dipilih di atas. Murni info -- ditampilkan di form Add Student, tidak divalidasi otomatis ke Jadwal Rutin.</div>

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
