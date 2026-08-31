@php
    // Locked-vs-free (pola "ina" project's University Album Photo
    // create()): create() mengunci SEMUA 4 field ini kalau datang
    // lengkap lewat query string (tombol "+ Add Jadwal" di index
    // Student). edit() TIDAK PERNAH mengunci (lihat
    // JadwalKelasController::edit()) -- $selected*Id cuma pernah ke-set
    // oleh create().
    $lockedBranchOfficeId = old('branch_office_id', $selectedBranchOfficeId ?? null);
    $lockedBranch = $lockedBranchOfficeId ? $branchOffices->firstWhere('id', $lockedBranchOfficeId) : null;

    $lockedMataPelajaranId = old('jadwal_mata_pelajaran_id', $selectedMataPelajaranId ?? null);
    $lockedMataPelajaran = $lockedMataPelajaranId ? $mataPelajarans->firstWhere('id', $lockedMataPelajaranId) : null;

    $lockedPengajarId = old('pengajar_id', $selectedPengajarId ?? null);
    $lockedPengajar = $lockedPengajarId ? $teamMembers->firstWhere('id', $lockedPengajarId) : null;

    $lockedStudentId = old('student_id', $selectedStudentId ?? null);
    $lockedStudent = $lockedStudentId ? $students->firstWhere('id', $lockedStudentId) : null;
@endphp

<div class="mb-3">
    <label class="form-label">Branch (opsional)</label>
    @if ($lockedBranch && !$errors->has('branch_office_id'))
        <input type="text" class="form-control" value="{{ $lockedBranch->name }}" disabled readonly>
        <input type="hidden" name="branch_office_id" value="{{ $lockedBranch->id }}">
        <div class="form-text">Jadwal Kelas ini akan dikaitkan ke branch di atas.</div>
    @elseif ($branchOffices->count() <= 1 && $branchOffices->isNotEmpty())
        {{-- Branch-locked member: satu-satunya opsi otomatis dipakai. --}}
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name }}" disabled>
        <div class="form-text">Otomatis terkunci ke branch Anda.</div>
    @else
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror">
            <option value="">- Tidak ditentukan -</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_office_id', $kelas->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_office_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Mata Pelajaran / Bidang (opsional)</label>
    @if ($lockedMataPelajaran && !$errors->has('jadwal_mata_pelajaran_id'))
        <input type="text" class="form-control" value="{{ $lockedMataPelajaran->name }}" disabled readonly>
        <input type="hidden" name="jadwal_mata_pelajaran_id" value="{{ $lockedMataPelajaran->id }}">
        <div class="form-text">Jadwal Kelas ini akan dikaitkan ke Mata Pelajaran / Bidang di atas.</div>
    @else
        <select name="jadwal_mata_pelajaran_id" class="form-select @error('jadwal_mata_pelajaran_id') is-invalid @enderror">
            <option value="">- Tidak ditentukan -</option>
            @foreach ($mataPelajarans as $mp)
                <option value="{{ $mp->id }}" @selected(old('jadwal_mata_pelajaran_id', $kelas->jadwal_mata_pelajaran_id ?? '') == $mp->id)>{{ $mp->name }}</option>
            @endforeach
        </select>
        @error('jadwal_mata_pelajaran_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    @endif
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Pengajar</label>
        @if ($lockedPengajar && !$errors->has('pengajar_id'))
            <input type="text" class="form-control" value="{{ $lockedPengajar->name }}" disabled readonly>
            <input type="hidden" name="pengajar_id" value="{{ $lockedPengajar->id }}">
            <div class="form-text">Terkunci ke pengajar di atas.</div>
        @else
            <select name="pengajar_id" class="form-select @error('pengajar_id') is-invalid @enderror" required>
                <option value="">- Pilih Pengajar -</option>
                @foreach ($teamMembers as $member)
                    <option value="{{ $member->id }}" @selected(old('pengajar_id', $kelas->pengajar_id ?? '') == $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
            @error('pengajar_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Murid</label>
        @if ($lockedStudent && !$errors->has('student_id'))
            <input type="text" class="form-control" value="{{ $lockedStudent->name }}" disabled readonly>
            <input type="hidden" name="student_id" value="{{ $lockedStudent->id }}">
            <div class="form-text">Terkunci ke murid di atas.</div>
        @else
            <select name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
                <option value="">- Pilih Murid -</option>
                @foreach ($students as $s)
                    <option value="{{ $s->id }}" @selected(old('student_id', $kelas->student_id ?? '') == $s->id)>{{ $s->name }} @if($s->mataPelajaran) — {{ $s->mataPelajaran->name }} @endif @if($s->pengajar) (diajar {{ $s->pengajar->name }}) @endif</option>
                @endforeach
            </select>
            @error('student_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        @endif
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Waktu Mulai (opsional)</label>
        <input type="datetime-local" name="start_time"
            value="{{ old('start_time', isset($kelas->start_time) && $kelas->start_time ? $kelas->start_time->format('Y-m-d\TH:i') : '') }}"
            class="form-control @error('start_time') is-invalid @enderror">
        @error('start_time')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Waktu Selesai (opsional)</label>
        <input type="datetime-local" name="end_time"
            value="{{ old('end_time', isset($kelas->end_time) && $kelas->end_time ? $kelas->end_time->format('Y-m-d\TH:i') : '') }}"
            class="form-control @error('end_time') is-invalid @enderror">
        @error('end_time')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Deskripsi (opsional)</label>
    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
        placeholder="Catatan tambahan untuk kelas ini...">{{ old('description', $kelas->description ?? '') }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $kelas->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $kelas->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
