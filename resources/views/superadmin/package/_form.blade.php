@csrf
@if (isset($package))
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
    <label for="name" class="form-label">Nama Package <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $package->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="category_application_id" class="form-label">Kategori Aplikasi <span class="text-danger">*</span></label>
    <select name="category_application_id" id="category_application_id" class="form-select" required>
        <option value="">— Pilih kategori —</option>
        @foreach ($categoryApplications as $category)
            <option value="{{ $category->id }}" @selected(old('category_application_id', $package->category_application_id ?? '') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $package->description ?? '') }}</textarea>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="duration" class="form-label">Durasi (hari) <span class="text-danger">*</span></label>
        <input type="number" name="duration" id="duration" class="form-control" min="1" placeholder="30" value="{{ old('duration', $package->duration ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label for="price" class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
        <input type="number" name="price" id="price" class="form-control" min="0" step="0.01" value="{{ old('price', $package->price ?? '') }}" required>
    </div>
</div>

<div class="mb-3">
    <label for="user_id" class="form-label">User (opsional)</label>
    <select name="user_id" id="user_id" class="form-select">
        <option value="">— Tidak terikat user —</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('user_id', $package->user_id ?? '') == $user->id)>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $package->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $package->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="mb-4 form-check">
    <input type="checkbox" name="is_featured" id="is_featured" class="form-check-input" value="1"
        @checked(old('is_featured', $package->is_featured ?? false))>
    <label for="is_featured" class="form-check-label">
        Tandai sebagai "TERPOPULER" di halaman depan (fe-konexa)
    </label>
    <div class="form-text">Boleh lebih dari satu package ditandai sekaligus kalau memang perlu.</div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('package.index') }}" class="btn btn-light">Batal</a>
</div>
