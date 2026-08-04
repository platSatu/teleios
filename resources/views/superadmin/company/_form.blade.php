@csrf
@if (isset($company))
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

@if (isset($company))
    <div class="row mb-3">
        <div class="col-sm-6">
            <div class="text-muted fs-12">Company ID (system-generated)</div>
            <div class="fw-semibold">{{ $company->company_id }}</div>
        </div>
        <div class="col-sm-6">
            <div class="text-muted fs-12">Slug</div>
            <div class="fw-semibold">{{ $company->slug }}</div>
        </div>
    </div>
    <hr class="mb-3">
@endif

<div class="d-flex align-items-center gap-3 mb-4">
    <img src="{{ isset($company) && $company->logo ? asset('storage/' . $company->logo) : asset('be') . '/assets/images/avatar/avatar-16.jpg' }}"
        alt="Logo" class="rounded" style="width: 64px; height: 64px; object-fit: cover;">
    <div>
        <label for="logo" class="form-label mb-1">Logo</label>
        <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/webp" class="form-control">
        <div class="form-text">JPG, PNG, atau WEBP. Maks 2MB. Kosongkan jika tidak ingin mengubah.</div>
    </div>
</div>

<div class="mb-3">
    <label for="user_id" class="form-label">Owner (User) <span class="text-danger">*</span></label>
    <select name="user_id" id="user_id" class="form-select" required>
        <option value="">-- Pilih User --</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('user_id', $company->user_id ?? '') == $user->id)>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="name" class="form-label">Nama Company <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $company->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $company->description ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="address" class="form-label">Alamat</label>
    <input type="text" name="address" id="address" class="form-control" value="{{ old('address', $company->address ?? '') }}">
</div>

<div class="row">
    <div class="col-sm-6 mb-3">
        <label for="phone" class="form-label">Telepon</label>
        <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone', $company->phone ?? '') }}">
    </div>
    <div class="col-sm-6 mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $company->email ?? '') }}">
    </div>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $company->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $company->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('company.index') }}" class="btn btn-light">Batal</a>
</div>
