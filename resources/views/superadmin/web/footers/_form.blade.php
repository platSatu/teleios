@csrf
@if (isset($footer))
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
    <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $footer->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="link" class="form-label">Link <span class="text-danger">*</span></label>
    <input type="text" name="link" id="link" class="form-control" value="{{ old('link', $footer->link ?? '') }}" placeholder="https://... atau /halaman" required>
</div>

<div class="mb-3 form-check">
    <input type="checkbox" name="target_blank" value="1" id="target_blank" class="form-check-input"
        @checked(old('target_blank', $footer->target_blank ?? false))>
    <label for="target_blank" class="form-check-label">Buka di tab baru (target="_blank")</label>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="column_width" class="form-label">Lebar Kolom <span class="text-danger">*</span></label>
        <select name="column_width" id="column_width" class="form-select" required>
            <option value="col-md-3" @selected(old('column_width', $footer->column_width ?? 'col-md-3') === 'col-md-3')>col-md-3 (4 blok per baris)</option>
            <option value="col-md-4" @selected(old('column_width', $footer->column_width ?? '') === 'col-md-4')>col-md-4 (3 blok per baris)</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="sort_order" class="form-label">Urutan Tampil</label>
        <input type="number" name="sort_order" id="sort_order" class="form-control" value="{{ old('sort_order', $footer->sort_order ?? 0) }}" min="0">
        <div class="form-text">Angka kecil tampil lebih dulu.</div>
    </div>
</div>

<hr class="my-3">
<h6 class="text-muted mb-3">Background (opsional)</h6>

<div class="mb-3">
    <label for="background_image" class="form-label">Gambar Background</label>
    @if (! empty($footer) && $footer->background_image)
        <div class="mb-2">
            <img src="{{ $footer->background_image_url }}" alt="Background" style="max-width: 200px;" class="rounded border">
        </div>
    @endif
    <input type="file" name="background_image" id="background_image" class="form-control" accept="image/*">
    <div class="form-text">Opsional. Maks 4MB.</div>
</div>

<div class="mb-4">
    <label for="background_color" class="form-label">Warna Background</label>
    <div class="input-group" style="max-width: 200px;">
        <input type="color" name="background_color" id="background_color" class="form-control form-control-color"
            value="{{ old('background_color', $footer->background_color ?? '#ffffff') }}">
    </div>
    <div class="form-text">Opsional — kosongkan/biarkan default kalau tidak perlu warna khusus.</div>
</div>

<hr class="my-3">

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="user_id" class="form-label">User (opsional)</label>
        <select name="user_id" id="user_id" class="form-select">
            <option value="">— Tidak terikat user —</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected(old('user_id', $footer->user_id ?? '') == $user->id)>
                    {{ $user->name }} ({{ $user->email }})
                </option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
        <select name="status" id="status" class="form-select" required>
            <option value="active" @selected(old('status', $footer->status ?? 'active') === 'active')>Active</option>
            <option value="inactive" @selected(old('status', $footer->status ?? '') === 'inactive')>Inactive</option>
        </select>
        <div class="form-text">Hanya Active yang tampil di frontend.</div>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('web.footers.index') }}" class="btn btn-light">Batal</a>
</div>
