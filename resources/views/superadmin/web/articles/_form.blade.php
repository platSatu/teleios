@csrf
@if (isset($article))
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
    <label for="web_category_article_id" class="form-label">Kategori <span class="text-danger">*</span></label>
    <select name="web_category_article_id" id="web_category_article_id" class="form-select" required>
        <option value="">— Pilih kategori —</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('web_category_article_id', $article->web_category_article_id ?? '') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="title" class="form-label">Judul <span class="text-danger">*</span></label>
    <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $article->title ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="images" class="form-label">Gambar {{ isset($article) ? '' : '*' }}</label>
    @if (! empty($article) && $article->images)
        <div class="mb-2">
            <img src="{{ $article->images_url }}" alt="{{ $article->title }}" style="max-width: 200px; max-height: 200px; object-fit: cover;" class="rounded border">
        </div>
    @endif
    <input type="file" name="images" id="images" class="form-control" accept="image/*" {{ isset($article) ? '' : 'required' }}>
    <div class="form-text">{{ isset($article) ? 'Kosongkan jika tidak ingin mengganti gambar.' : '' }} Maks 4MB, otomatis di-resize.</div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi / Isi Artikel <span class="text-danger">*</span></label>
    <textarea name="description" id="description" class="form-control" rows="8" required>{{ old('description', $article->description ?? '') }}</textarea>
</div>

<hr class="my-4">
<h6 class="text-muted mb-3">SEO</h6>

<div class="mb-3">
    <label for="meta_tags" class="form-label">Meta Tags</label>
    <div class="border rounded p-2" style="max-height: 160px; overflow-y: auto;">
        @forelse ($metaTags as $tag)
            @php
                $selectedTags = old('meta_tags', isset($article) ? $article->metaTags->pluck('id')->all() : []);
            @endphp
            <div class="form-check">
                <input type="checkbox" name="meta_tags[]" value="{{ $tag->id }}" id="meta_tag_{{ $tag->id }}" class="form-check-input"
                    @checked(in_array($tag->id, $selectedTags))>
                <label for="meta_tag_{{ $tag->id }}" class="form-check-label">{{ $tag->name }}</label>
            </div>
        @empty
            <p class="text-muted mb-0">Belum ada meta tag. <a href="{{ route('web.meta-tags.create') }}">Tambah meta tag</a>.</p>
        @endforelse
    </div>
</div>

<div class="mb-3">
    <label for="meta_keywords" class="form-label">Meta Keywords</label>
    <input type="text" name="meta_keywords" id="meta_keywords" class="form-control" value="{{ old('meta_keywords', $article->meta_keywords ?? '') }}" placeholder="pisahkan dengan koma">
</div>

<div class="mb-3">
    <label for="meta_descriptions" class="form-label">Meta Description</label>
    <textarea name="meta_descriptions" id="meta_descriptions" class="form-control" rows="2">{{ old('meta_descriptions', $article->meta_descriptions ?? '') }}</textarea>
    <div class="form-text">Kosongkan untuk otomatis memakai Deskripsi di atas.</div>
</div>

<div class="mb-4">
    <label for="meta_images" class="form-label">Meta Image (Open Graph)</label>
    @if (! empty($article) && $article->meta_images)
        <div class="mb-2">
            <img src="{{ $article->meta_images_url }}" alt="Meta image" style="max-width: 200px; max-height: 200px; object-fit: cover;" class="rounded border">
        </div>
    @endif
    <input type="file" name="meta_images" id="meta_images" class="form-control" accept="image/*">
    <div class="form-text">Kosongkan untuk otomatis memakai Gambar di atas.</div>
</div>

<hr class="my-4">

<div class="mb-3">
    <label for="date_publish" class="form-label">Tanggal Publish</label>
    <input type="datetime-local" name="date_publish" id="date_publish" class="form-control"
        value="{{ old('date_publish', isset($article) && $article->date_publish ? $article->date_publish->format('Y-m-d\TH:i') : '') }}">
</div>

<div class="mb-3">
    <label for="user_id" class="form-label">User (opsional)</label>
    <select name="user_id" id="user_id" class="form-select">
        <option value="">— Tidak terikat user —</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('user_id', $article->user_id ?? '') == $user->id)>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $article->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $article->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('web.articles.index') }}" class="btn btn-light">Batal</a>
</div>
