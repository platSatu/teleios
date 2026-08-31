@php
    // Locked-vs-free (pola "ina" project's University Album Photo):
    // create() mengunci jadwal_mata_pelajaran_id & pengajar_id kalau
    // datang dari query string (tombol "+ Add Student" di index
    // Pengajar). edit() TIDAK PERNAH mengunci (lihat
    // JadwalStudentController::edit()) -- $selectedMataPelajaranId/
    // $selectedPengajarId cuma pernah ke-set oleh create().
    //
    // Branch TIDAK pernah datang terkunci lewat drill-down (tombol
    // "+ Add Student" cuma bawa jadwal_mata_pelajaran_id & pengajar_id,
    // branch-nya otomatis mengikuti mata pelajaran yang dipilih di
    // JadwalStudentController::store()/update()) -- tapi tetap
    // ditampilkan sebagai select manual di sini supaya akses langsung
    // lewat menu sidebar "Student" bisa pilih branch sendiri tanpa
    // harus lewat index Branch/Mata Pelajaran dulu.
    $lockedBranchOfficeId = old('branch_office_id', $selectedBranchOfficeId ?? null);
    $lockedBranch = $lockedBranchOfficeId ? $branchOffices->firstWhere('id', $lockedBranchOfficeId) : null;

    $lockedMataPelajaranId = old('jadwal_mata_pelajaran_id', $selectedMataPelajaranId ?? null);
    $lockedMataPelajaran = $lockedMataPelajaranId ? $mataPelajarans->firstWhere('id', $lockedMataPelajaranId) : null;

    $lockedPengajarId = old('pengajar_id', $selectedPengajarId ?? null);
    $lockedPengajar = $lockedPengajarId ? $teamMembers->firstWhere('id', $lockedPengajarId) : null;
@endphp

<div class="mb-3">
    <label class="form-label">Branch (opsional)</label>
    @if ($lockedBranch && !$errors->has('branch_office_id'))
        <input type="text" class="form-control" value="{{ $lockedBranch->name }}" disabled readonly>
        <input type="hidden" name="branch_office_id" value="{{ $lockedBranch->id }}">
        <div class="form-text">
            Student ini akan dikaitkan ke branch di atas.
            @if ($branchOffices->count() > 1)
                <a href="{{ route('jadwal.student.create') }}">Ganti branch</a>
            @endif
        </div>
    @elseif ($branchOffices->count() <= 1 && $branchOffices->isNotEmpty())
        {{-- Branch-locked member: satu-satunya opsi otomatis dipakai. --}}
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name }}" disabled>
        <div class="form-text">Otomatis terkunci ke branch Anda.</div>
    @else
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror">
            <option value="">- Ikuti Mata Pelajaran / Bidang -</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_office_id', $student->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_office_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Kalau tidak dipilih, ikut branch dari Mata Pelajaran / Bidang yang dipilih di bawah.</div>
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Mata Pelajaran / Bidang</label>
    @if ($lockedMataPelajaran && !$errors->has('jadwal_mata_pelajaran_id'))
        <input type="text" class="form-control" value="{{ $lockedMataPelajaran->name }}" disabled readonly>
        <input type="hidden" name="jadwal_mata_pelajaran_id" value="{{ $lockedMataPelajaran->id }}">
        <div class="form-text">
            Student ini akan dikaitkan ke Mata Pelajaran / Bidang di atas.
            <a href="{{ route('jadwal.student.create') }}">Ganti Mata Pelajaran / Bidang</a>
        </div>
    @else
        <select name="jadwal_mata_pelajaran_id" class="form-select @error('jadwal_mata_pelajaran_id') is-invalid @enderror" required>
            <option value="">- Pilih Mata Pelajaran / Bidang -</option>
            @foreach ($mataPelajarans as $mp)
                <option value="{{ $mp->id }}" @selected(old('jadwal_mata_pelajaran_id', $student->jadwal_mata_pelajaran_id ?? '') == $mp->id)>{{ $mp->name }}</option>
            @endforeach
        </select>
        @error('jadwal_mata_pelajaran_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Pengajar</label>
    @if ($lockedPengajar && !$errors->has('pengajar_id'))
        <input type="text" class="form-control" value="{{ $lockedPengajar->name }}" disabled readonly>
        <input type="hidden" name="pengajar_id" value="{{ $lockedPengajar->id }}">
        <div class="form-text">
            Student ini akan dikaitkan ke Pengajar di atas.
            <a href="{{ route('jadwal.student.create', array_filter(['jadwal_mata_pelajaran_id' => $lockedMataPelajaranId])) }}">Ganti Pengajar</a>
        </div>
    @else
        <select name="pengajar_id" class="form-select @error('pengajar_id') is-invalid @enderror" required>
            <option value="">- Pilih Pengajar -</option>
            @foreach ($teamMembers as $member)
                <option value="{{ $member->id }}" @selected(old('pengajar_id', $student->pengajar_id ?? '') == $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
        @error('pengajar_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Nama Student</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $student->name ?? '') }}" placeholder="Nama murid" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $student->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $student->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
