<div class="mb-3">
    <label class="form-label">Nama Kelompok</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $category->name ?? '') }}" placeholder="Misal: Pelanggan VIP" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Branch (opsional)</label>
    @if ($branchOffices->count() <= 1 && $branchOffices->isNotEmpty())
        {{-- Branch-locked member: exactly one option, so it's effectively
             forced — same "no picker, straight to the one they're allowed"
             treatment as CompanyUserController's create form. --}}
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name }}" disabled>
        <div class="form-text">Kelompok ini otomatis terkunci ke branch Anda.</div>
    @else
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror">
            <option value="">- Semua Branch -</option>
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
