<div class="mb-3">
    <label class="form-label">Nama</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $pelanggan->name ?? '') }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">No. Telepon (WhatsApp)</label>
    <input type="text" name="phone_number" class="form-control @error('phone_number') is-invalid @enderror"
        value="{{ old('phone_number', $pelanggan->phone_number ?? '') }}" placeholder="628xxxxxxxxxx">
    @error('phone_number')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Email (opsional)</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
        value="{{ old('email', $pelanggan->email ?? '') }}">
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@php
    $lockedBranchOfficeId = old('branch_office_id', $selectedBranchOfficeId ?? ($pelanggan->branch_office_id ?? null));
    $lockedBranch = $lockedBranchOfficeId ? $branchOffices->firstWhere('id', $lockedBranchOfficeId) : null;
@endphp
<div class="mb-3">
    <label class="form-label">Branch</label>
    @if ($lockedBranch && !$errors->has('branch_office_id') && ($pelanggan ?? null))
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
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
        <option value="active" @selected(old('status', $pelanggan->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $pelanggan->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
</div>
