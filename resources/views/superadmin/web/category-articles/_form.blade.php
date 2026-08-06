@csrf
@if (isset($categoryArticle))
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
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $categoryArticle->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="images" class="form-label">Gambar</label>
    @if (! empty($categoryArticle) && $categoryArticle->images)
        <div class="mb-2">
            <img src="{{ $categoryArticle->images_url }}" alt="{{ $categoryArticle->name }}" style="max-width: 200px; max-height: 200px; object-fit: cover;" class="rounded border">
        </div>
    @endif
    <input type="file" name="images" id="images" class="form-control" accept="image/*">
    <div class="form-text">Kosongkan jika tidak ingin mengganti gambar. Maks 4MB, otomatis di-resize.</div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="4">{{ old('description', $categoryArticle->description ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="date_publish" class="form-label">Tanggal Publish</label>
    <input type="datetime-local" name="date_publish" id="date_publish" class="form-control"
        value="{{ old('date_publish', isset($categoryArticle) && $categoryArticle->date_publish ? $categoryArticle->date_publish->format('Y-m-d\TH:i') : '') }}">
</div>

<div class="mb-3">
    <label for="user_id" class="form-label">User (opsional)</label>
    <select name="user_id" id="user_id" class="form-select">
        <option value="">— Tidak terikat user —</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('user_id', $categoryArticle->user_id ?? '') == $user->id)>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $categoryArticle->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $categoryArticle->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('web.category-articles.index') }}" class="btn btn-light">Batal</a>
</div>
