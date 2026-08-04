@csrf
@if (isset($companyRole))
    @method('PUT')
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-3">
    <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
    <select name="company_id" id="company_id" class="form-select" required>
        <option value="">-- Pilih Company --</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected(old('company_id', $companyRole->company_id ?? '') == $company->id)>
                {{ $company->name }} ({{ $company->company_id }})
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="name" class="form-label">Nama Role <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $companyRole->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $companyRole->description ?? '') }}</textarea>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $companyRole->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $companyRole->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('company-role.index') }}" class="btn btn-light">Batal</a>
</div>
