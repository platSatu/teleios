@csrf
@if (isset($applicationMenu))
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
    <label for="category_application_id" class="form-label">Category Application <span class="text-danger">*</span></label>
    <select name="category_application_id" id="category_application_id" class="form-select" required>
        <option value="">-- Pilih Category Application --</option>
        @foreach ($categoryApplications as $category)
            <option value="{{ $category->id }}" @selected(old('category_application_id', $applicationMenu->category_application_id ?? '') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="name" class="form-label">Nama Menu <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $applicationMenu->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="route_name" class="form-label">Route Name</label>
    <input type="text" name="route_name" id="route_name" class="form-control" value="{{ old('route_name', $applicationMenu->route_name ?? '') }}" placeholder="mis. chat.connect-device.index atau profile.branch-offices.index">
    <div class="form-text">
        Dipakai untuk gating akses per role (sidebar &amp; middleware <code>menu.access</code>) — harus cocok dengan nama route Laravel yang sebenarnya, atau setidaknya berbagi dua segmen pertama dengannya (mis. <code>profile.branch-offices</code>). Kosongkan kalau menu ini murni label/grouping tanpa halaman sendiri.
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="icon" class="form-label">Icon</label>
        <input type="text" name="icon" id="icon" class="form-control" value="{{ old('icon', $applicationMenu->icon ?? '') }}" placeholder="mis. ri-smartphone-line">
    </div>
    <div class="col-md-4 mb-3">
        <label for="sort_order" class="form-label">Urutan Tampil</label>
        <input type="number" name="sort_order" id="sort_order" class="form-control" value="{{ old('sort_order', $applicationMenu->sort_order ?? 0) }}">
    </div>
    <div class="col-md-4 mb-3">
        <label for="parent_id" class="form-label">Parent Menu (opsional)</label>
        <select name="parent_id" id="parent_id" class="form-select">
            <option value="">— Tidak ada (menu utama) —</option>
            @foreach ($parentCandidates as $parent)
                <option value="{{ $parent->id }}" @selected(old('parent_id', $applicationMenu->parent_id ?? '') == $parent->id)>
                    {{ $parent->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $applicationMenu->description ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="user_id" class="form-label">User (opsional)</label>
    <select name="user_id" id="user_id" class="form-select">
        <option value="">— Tidak terikat user —</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('user_id', $applicationMenu->user_id ?? '') == $user->id)>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $applicationMenu->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $applicationMenu->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('application-menu.index') }}" class="btn btn-light">Batal</a>
</div>
