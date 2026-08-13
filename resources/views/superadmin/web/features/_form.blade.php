@csrf
@if (isset($feature))
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
    <label for="name" class="form-label">Nama Fitur <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $feature->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $feature->description ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="images" class="form-label">Gambar</label>
    @if (! empty($feature) && $feature->images)
        <div class="mb-2">
            <img src="{{ $feature->images_url }}" alt="{{ $feature->name }}" style="max-width: 240px;" class="rounded border">
        </div>
    @endif
    <input type="file" name="images" id="images" class="form-control" accept="image/*">
    <div class="form-text">Opsional. Maks 4MB.</div>
</div>

<div class="mb-3">
    <label for="user_id" class="form-label">User (opsional)</label>
    <select name="user_id" id="user_id" class="form-select">
        <option value="">— Tidak terikat user —</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('user_id', $feature->user_id ?? '') == $user->id)>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $feature->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $feature->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('web.features.index') }}" class="btn btn-light">Batal</a>
</div>
