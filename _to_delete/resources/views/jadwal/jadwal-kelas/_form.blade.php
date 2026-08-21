@csrf
@if($item)
    @method('PUT')
@endif

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Cabang <span class="text-danger">*</span></label>
        @if ($lockedBranchOfficeId ?? null)
            @php $lockedBranch = $branchOffices->firstWhere('id', $lockedBranchOfficeId); @endphp
            <input type="hidden" name="branch_office_id" value="{{ $lockedBranchOfficeId }}">
            <input type="text" class="form-control" value="{{ $lockedBranch->name ?? '-' }}" disabled>
            <div class="form-text">Dikunci karena dibuat dari halaman Mata Pelajaran.</div>
        @elseif ($branchOffices->count() > 1)
            <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror" required>
                <option value="">-- Pilih Cabang --</option>
                @foreach ($branchOffices as $branch)
                    <option value="{{ $branch->id }}" @selected(old('branch_office_id', $item->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        @else
            <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id ?? '' }}">
            <input type="text" class="form-control" value="{{ $branchOffices->first()->name ?? '-' }}" disabled>
        @endif
        @error('branch_office_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
        @if ($lockedMataPelajaranId ?? null)
            @php $lockedMp = $mataPelajaranList->firstWhere('id', $lockedMataPelajaranId); @endphp
            <input type="hidden" name="mata_pelajaran_id" value="{{ $lockedMataPelajaranId }}">
            <input type="text" class="form-control" value="{{ $lockedMp->name ?? '-' }}" disabled>
            <div class="form-text">Dikunci karena dibuat dari halaman Mata Pelajaran.</div>
        @else
            <select name="mata_pelajaran_id" id="mataPelajaranSelect" class="form-select @error('mata_pelajaran_id') is-invalid @enderror" required>
                <option value="">-- Pilih Mata Pelajaran --</option>
                @foreach ($mataPelajaranList as $mp)
                    <option value="{{ $mp->id }}" data-durasi="{{ $mp->durasi_menit }}" @selected(old('mata_pelajaran_id', $item->mata_pelajaran_id ?? '') == $mp->id)>{{ $mp->name }}{{ $mp->durasi_menit ? ' ('.$mp->durasi_menit.' menit)' : '' }}</option>
                @endforeach
            </select>
            @if ($mataPelajaranList->isEmpty())
                <div class="form-text text-warning">Belum ada mata pelajaran untuk cabang ini — <a href="{{ route('jadwal.mata-pelajaran.create') }}">tambah dulu</a>.</div>
            @endif
        @endif
        @error('mata_pelajaran_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Nama Kelas (opsional)</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $item->name ?? '') }}" maxlength="255" placeholder="Misal: Matematika Kelas A">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Hari <span class="text-danger">*</span></label>
        <select name="hari" class="form-select @error('hari') is-invalid @enderror" required>
            <option value="">-- Pilih Hari --</option>
            @foreach ($hariList as $hari)
                <option value="{{ $hari }}" @selected(old('hari', $item->hari ?? '') === $hari)>{{ $hari }}</option>
            @endforeach
        </select>
        @error('hari')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
        <input type="time" name="jam_mulai" id="jamMulaiInput" class="form-control @error('jam_mulai') is-invalid @enderror" value="{{ old('jam_mulai', isset($item->jam_mulai) ? substr($item->jam_mulai, 0, 5) : '') }}" required>
        @error('jam_mulai')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Jam Selesai <span class="text-danger">*</span></label>
        <input type="time" name="jam_selesai" id="jamSelesaiInput" class="form-control @error('jam_selesai') is-invalid @enderror" value="{{ old('jam_selesai', isset($item->jam_selesai) ? substr($item->jam_selesai, 0, 5) : '') }}" required>
        @error('jam_selesai')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Terisi otomatis dari durasi standar mata pelajaran (bisa diubah manual).</div>
    </div>
</div>

<script>
    // Auto-suggest jam_selesai = jam_mulai + durasi standar mata
    // pelajaran (see Jadwal\MataPelajaranController's durasi_menit) —
    // only fills in an EMPTY jam_selesai, never overwrites something the
    // admin already typed/edited themselves.
    (function () {
        var mpSelect = document.getElementById('mataPelajaranSelect');
        var jamMulai = document.getElementById('jamMulaiInput');
        var jamSelesai = document.getElementById('jamSelesaiInput');

        if (! mpSelect || ! jamMulai || ! jamSelesai) return;

        function suggestJamSelesai() {
            if (jamSelesai.value) return; // don't clobber a manual edit

            var opt = mpSelect.options[mpSelect.selectedIndex];
            var durasi = opt ? parseInt(opt.getAttribute('data-durasi'), 10) : NaN;

            if (! jamMulai.value || isNaN(durasi) || durasi <= 0) return;

            var parts = jamMulai.value.split(':');
            var start = new Date(2000, 0, 1, parseInt(parts[0], 10), parseInt(parts[1], 10));
            var end = new Date(start.getTime() + durasi * 60000);

            var hh = String(end.getHours()).padStart(2, '0');
            var mm = String(end.getMinutes()).padStart(2, '0');
            jamSelesai.value = hh + ':' + mm;
        }

        mpSelect.addEventListener('change', suggestJamSelesai);
        jamMulai.addEventListener('change', suggestJamSelesai);
    })();
</script>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Guru (Pengajar)</label>
        <select name="guru_user_id" class="form-select @error('guru_user_id') is-invalid @enderror">
            <option value="">-- Belum Ditentukan --</option>
            @foreach ($guruList as $guru)
                <option value="{{ $guru->id }}" @selected(old('guru_user_id', $item->guru_user_id ?? '') == $guru->id)>{{ $guru->name }}{{ $guru->handphone ? ' — ' . $guru->handphone : '' }}</option>
            @endforeach
        </select>
        @error('guru_user_id')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <div class="form-text">1 guru bisa mengajar di beberapa jadwal kelas, termasuk di cabang lain.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Kapasitas (opsional)</label>
        <input type="number" name="kapasitas" class="form-control @error('kapasitas') is-invalid @enderror" value="{{ old('kapasitas', $item->kapasitas ?? '') }}" min="1" max="1000">
        @error('kapasitas')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

@if (! $item)
<div class="mb-3">
    <label class="form-label">Murid (opsional)</label>
    <select name="murid_user_id[]" class="form-select @error('murid_user_id') is-invalid @enderror @error('murid_user_id.*') is-invalid @enderror" multiple size="6">
        @foreach ($muridList as $murid)
            <option value="{{ $murid->id }}" @selected(collect(old('murid_user_id', []))->contains($murid->id))>{{ $murid->name }}{{ $murid->handphone ? ' — ' . $murid->handphone : '' }}</option>
        @endforeach
    </select>
    @error('murid_user_id')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('murid_user_id.*')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <div class="form-text">Ctrl/Cmd+klik untuk pilih lebih dari satu. Murid yang dipilih langsung didaftarkan ke kelas ini dan menerima notifikasi WA. Bisa juga ditambahkan/dikelola nanti dari halaman detail kelas.</div>
</div>
@endif

<div class="mb-3">
    <label class="form-label">Device WhatsApp Pengirim Notifikasi</label>
    <select class="form-select wa-device-select @error('device_id') is-invalid @enderror" name="device_id" data-selected="{{ old('device_id', $item->device_id ?? '') }}">
        <option value="">Memuat device...</option>
    </select>
    @error('device_id')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    <div class="form-text">Device ini yang akan mengirim reminder & notifikasi perubahan jadwal ke guru/murid.</div>
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $item->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $item->status ?? '') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('jadwal.jadwal-kelas.index') }}" class="btn btn-light">Batal</a>
</div>

@include('chat.partials.device-select-script')
