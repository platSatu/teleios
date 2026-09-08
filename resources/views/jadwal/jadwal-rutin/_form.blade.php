<input type="hidden" name="student_id" value="{{ $student->id }}">

<div class="mb-3">
    <label class="form-label">Kategori &amp; Grade</label>
    <select name="jadwal_grade_id" class="form-select @error('jadwal_grade_id') is-invalid @enderror" required>
        <option value="">- Pilih Grade -</option>
        @foreach($mataPelajarans as $mp)
            @foreach($mp->kategoris as $kat)
                @if($kat->grades->isNotEmpty())
                    <optgroup label="{{ $mp->name }} — {{ $kat->name }}">
                        @foreach($kat->grades as $grade)
                            <option value="{{ $grade->id }}" @selected(old('jadwal_grade_id', $rutin->jadwal_grade_id ?? '') == $grade->id)>
                                {{ $grade->name }} — Rp {{ number_format($grade->hargaPerSesi($branchSetting->sesi_per_bulan_default ?? null), 0, ',', '.') }} / sesi
                            </option>
                        @endforeach
                    </optgroup>
                @endif
            @endforeach
        @endforeach
    </select>
    @error('jadwal_grade_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if($mataPelajarans->every(fn($mp) => $mp->kategoris->every(fn($kat) => $kat->grades->isEmpty())))
        <div class="form-text text-warning">Belum ada Grade aktif. Tambahkan lewat menu Jadwal &gt; Kelas &gt; Kategori &gt; Grade terlebih dahulu.</div>
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Pengajar</label>
    <select name="pengajar_id" class="form-select @error('pengajar_id') is-invalid @enderror" required>
        <option value="">- Pilih Pengajar -</option>
        @foreach($teamMembers as $member)
            <option value="{{ $member->id }}" @selected(old('pengajar_id', $rutin->pengajar_id ?? '') == $member->id)>{{ $member->name }}</option>
        @endforeach
    </select>
    @error('pengajar_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Ruangan (opsional)</label>
    <select name="jadwal_ruangan_id" class="form-select @error('jadwal_ruangan_id') is-invalid @enderror">
        <option value="">- Tanpa Ruangan Tetap -</option>
        @foreach($ruangans as $ruangan)
            <option value="{{ $ruangan->id }}" @selected(old('jadwal_ruangan_id', $rutin->jadwal_ruangan_id ?? '') == $ruangan->id)>{{ $ruangan->name }}</option>
        @endforeach
    </select>
    @error('jadwal_ruangan_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Hari</label>
        @php $selectedHari = old('hari', $rutin?->hari); @endphp
        <select name="hari" class="form-select @error('hari') is-invalid @enderror" required>
            <option value="">- Pilih Hari -</option>
            @foreach(\App\Models\JadwalRutin::HARI_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($selectedHari !== null && (int) $selectedHari === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('hari')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Jam Mulai</label>
        <input type="time" name="jam_mulai" class="form-control @error('jam_mulai') is-invalid @enderror"
            value="{{ old('jam_mulai', $rutin ? substr($rutin->jam_mulai, 0, 5) : '') }}" required>
        @error('jam_mulai')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Durasi (menit)</label>
        <input type="number" min="5" max="600" name="durasi_menit" class="form-control @error('durasi_menit') is-invalid @enderror"
            value="{{ old('durasi_menit', $rutin->durasi_menit ?? '') }}"
            placeholder="Default: {{ $branchSetting->durasi_sesi_default_menit ?? 30 }} menit">
        @error('durasi_menit')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Kosongkan untuk pakai default branch ({{ $branchSetting->durasi_sesi_default_menit ?? 30 }} menit).</div>
    </div>
</div>

@if(!$branchSetting)
    <div class="alert alert-warning">
        Branch <strong>{{ $student->branchOffice?->name ?? '-' }}</strong> belum punya Jam Operasional. Atur dulu lewat menu Jadwal &gt; Branch &gt; Jam Operasional sebelum menyimpan Jadwal Rutin ini.
    </div>
@else
    <div class="form-text mb-3">
        Jam operasional branch: {{ substr($branchSetting->jam_buka, 0, 5) }}–{{ substr($branchSetting->jam_tutup, 0, 5) }}
        @if($branchSetting->jam_istirahat_mulai)
            (istirahat {{ substr($branchSetting->jam_istirahat_mulai, 0, 5) }}–{{ substr($branchSetting->jam_istirahat_selesai, 0, 5) }})
        @endif
    </div>
@endif

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Efektif Mulai</label>
        <input type="date" name="efektif_mulai" class="form-control @error('efektif_mulai') is-invalid @enderror"
            value="{{ old('efektif_mulai', $rutin?->efektif_mulai?->format('Y-m-d') ?? now()->toDateString()) }}" required>
        @error('efektif_mulai')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Efektif Selesai (opsional)</label>
        <input type="date" name="efektif_selesai" class="form-control @error('efektif_selesai') is-invalid @enderror"
            value="{{ old('efektif_selesai', $rutin?->efektif_selesai?->format('Y-m-d') ?? '') }}">
        @error('efektif_selesai')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Kosongkan kalau masih berlaku terus.</div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $rutin->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $rutin->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
