<div class="mb-3">
    <label class="form-label">Nama Kategori</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $category->name ?? '') }}" placeholder="Misal: Uang Sekolah, Uang Pangkal, Uang Seragam" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@php
    $lockedBranchOfficeId = old('branch_office_id', $selectedBranchOfficeId ?? ($category->branch_office_id ?? null));
    $lockedBranch = $lockedBranchOfficeId ? $branchOffices->firstWhere('id', $lockedBranchOfficeId) : null;
@endphp
<div class="mb-3">
    <label class="form-label">Branch</label>
    @if ($lockedBranch && !$errors->has('branch_office_id') && ($category ?? null))
        <input type="text" class="form-control" value="{{ $lockedBranch->name }}" disabled readonly>
        <input type="hidden" name="branch_office_id" value="{{ $lockedBranch->id }}">
    @elseif ($branchOffices->count() <= 1 && $branchOffices->isNotEmpty())
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name }}" disabled>
    @else
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror" required>
            <option value="">- Pilih Branch -</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected($lockedBranchOfficeId == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_office_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Nominal Default (opsional)</label>
    <input type="number" step="0.01" min="0" name="default_amount" class="form-control @error('default_amount') is-invalid @enderror"
        value="{{ old('default_amount', $category->default_amount ?? '') }}" placeholder="Cuma acuan awal -- tiap Tagihan boleh isi nominal beda">
    @error('default_amount')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="form-check mb-2">
    <input type="checkbox" name="is_recurring" id="is_recurring" class="form-check-input" value="1"
        @checked(old('is_recurring', $category->is_recurring ?? false))>
    <label class="form-check-label" for="is_recurring">Berulang tiap bulan (label saja -- Tagihan baru tetap dibuat manual tiap periode)</label>
</div>

<div class="form-check mb-3">
    <input type="checkbox" name="denda_enabled" id="denda_enabled" class="form-check-input" value="1"
        @checked(old('denda_enabled', $category->denda_enabled ?? false))>
    <label class="form-check-label" for="denda_enabled">Aktifkan denda keterlambatan secara default untuk kategori ini</label>
    <div class="form-text">Bisa dikelola lebih detail (aturan bertingkat) di bawah setelah kategori disimpan. Tiap Tagihan tetap bisa override on/off sendiri.</div>
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
