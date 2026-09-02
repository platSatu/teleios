<div class="mb-3">
    <label class="form-label">Nama</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $category->name ?? '') }}" placeholder="Misal: Pendaftaran Kursus, Survey Kepuasan" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@php
    // Locked-vs-picker (pola sama dengan jadwal-mata-pelajaran/_form.blade.php):
    // kalau create() dibuka dengan ?branch_office_id=... branch dikunci.
    // Tanpa itu -- atau saat edit(), yang tidak pernah mengirim
    // $selectedBranchOfficeId -- tetap dropdown/forced-single. Beda dari
    // pola Jadwal: branch_office_id di sini WAJIB (tidak ada opsi kosong).
    $lockedBranchOfficeId = old('branch_office_id', $selectedBranchOfficeId ?? null);
    $lockedBranch = $lockedBranchOfficeId ? $branchOffices->firstWhere('id', $lockedBranchOfficeId) : null;
@endphp
<div class="mb-3">
    <label class="form-label">Branch</label>
    @if ($lockedBranch && !$errors->has('branch_office_id'))
        <input type="text" class="form-control" value="{{ $lockedBranch->name }}" disabled readonly>
        <input type="hidden" name="branch_office_id" value="{{ $lockedBranch->id }}">
        <div class="form-text">
            Form Category ini akan dikaitkan ke branch di atas.
            @if ($branchOffices->count() > 1)
                <a href="{{ route('form.category.create') }}">Ganti branch</a>
            @endif
        </div>
    @elseif ($branchOffices->count() <= 1 && $branchOffices->isNotEmpty())
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name }}" disabled>
        <div class="form-text">Otomatis terkunci ke branch Anda.</div>
    @else
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror" required>
            <option value="">- Pilih Branch -</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_office_id', $category->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_office_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $category->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $category->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
