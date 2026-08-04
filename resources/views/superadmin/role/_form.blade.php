@csrf
@if (isset($role))
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
    <label for="name" class="form-label">Nama Role <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $role->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="guard_name" class="form-label">Guard <span class="text-danger">*</span></label>
    <input type="text" name="guard_name" id="guard_name" class="form-control" value="{{ old('guard_name', $role->guard_name ?? 'web') }}" required>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $role->description ?? '') }}</textarea>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $role->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $role->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('roles.index') }}" class="btn btn-light">Batal</a>
</div>
