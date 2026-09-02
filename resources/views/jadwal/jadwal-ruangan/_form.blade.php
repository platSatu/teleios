<div class="mb-3">
    <label class="form-label">Nama Ruangan</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $ruangan->name ?? '') }}" placeholder="Misal: Ruangan 1, Studio A" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Catatan Kegunaan (opsional)</label>
    <textarea name="catatan_kegunaan" rows="3" class="form-control @error('catatan_kegunaan') is-invalid @enderror"
        placeholder="Mis: cocok untuk piano & gitar akustik. Murni info -- ruangan tetap bisa dipakai kelas apa saja.">{{ old('catatan_kegunaan', $ruangan->catatan_kegunaan ?? '') }}</textarea>
    @error('catatan_kegunaan')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">Sekadar saran/info saat memilih ruangan di Jadwal Rutin -- ruangan ini TIDAK dikunci ke satu kelas tertentu.</div>
</div>

@php
    $lockedBranchOfficeId = old('branch_office_id', $selectedBranchOfficeId ?? null);
    $lockedBranch = $lockedBranchOfficeId ? $branchOffices->firstWhere('id', $lockedBranchOfficeId) : null;
@endphp
<div class="mb-3">
    <label class="form-label">Branch</label>
    @if ($lockedBranch && !$errors->has('branch_office_id'))
        <input type="text" class="form-control" value="{{ $lockedBranch->name }}" disabled readonly>
        <input type="hidden" name="branch_office_id" value="{{ $lockedBranch->id }}">
    @elseif ($branchOffices->count() <= 1 && $branchOffices->isNotEmpty())
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name }}" disabled>
        <div class="form-text">Otomatis terkunci ke branch Anda.</div>
    @else
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror" required>
            <option value="">- Pilih Branch -</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_office_id', $ruangan->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
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
        <option value="active" @selected(old('status', $ruangan->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $ruangan->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
