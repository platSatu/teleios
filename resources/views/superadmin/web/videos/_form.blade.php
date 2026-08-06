@csrf
@if (isset($video))
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
    <label for="web_category_video_id" class="form-label">Kategori <span class="text-danger">*</span></label>
    <select name="web_category_video_id" id="web_category_video_id" class="form-select" required>
        <option value="">— Pilih kategori —</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('web_category_video_id', $video->web_category_video_id ?? '') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="title" class="form-label">Judul <span class="text-danger">*</span></label>
    <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $video->title ?? '') }}" required>
</div>

<div class="alert alert-info">
    Isi salah satu di bawah ini: upload file video, atau isi link YouTube.
</div>

<div class="mb-3">
    <label for="videos" class="form-label">Upload File Video</label>
    @if (! empty($video) && $video->videos)
        <div class="mb-2">
            <video src="{{ $video->videos_url }}" controls style="max-width: 320px;" class="rounded border"></video>
        </div>
    @endif
    <input type="file" name="videos" id="videos" class="form-control" accept="video/*">
    <div class="form-text">Format mp4/mov/avi/wmv/mkv/webm, maks 100MB.</div>
</div>

<div class="mb-3">
    <label for="link_youtube" class="form-label">Link YouTube</label>
    <input type="url" name="link_youtube" id="link_youtube" class="form-control" value="{{ old('link_youtube', $video->link_youtube ?? '') }}" placeholder="https://www.youtube.com/watch?v=... atau https://youtu.be/...">
</div>

<div class="mb-3">
    <label for="thumbnail" class="form-label">Thumbnail</label>
    @if (! empty($video) && $video->thumbnail)
        <div class="mb-2">
            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" style="max-width: 220px;" class="rounded border">
        </div>
    @endif
    <input type="file" name="thumbnail" id="thumbnail" class="form-control" accept="image/*">
    <div class="form-text">Kosongkan jika tidak ingin mengganti thumbnail. Otomatis di-crop 16:9.</div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="5">{{ old('description', $video->description ?? '') }}</textarea>
</div>

<hr class="my-4">
<h6 class="text-muted mb-3">SEO</h6>

<div class="mb-3">
    <label for="meta_tags" class="form-label">Meta Tags</label>
    <div class="border rounded p-2" style="max-height: 160px; overflow-y: auto;">
        @forelse ($metaTags as $tag)
            @php
                $selectedTags = old('meta_tags', isset($video) ? $video->metaTags->pluck('id')->all() : []);
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
    <input type="text" name="meta_keywords" id="meta_keywords" class="form-control" value="{{ old('meta_keywords', $video->meta_keywords ?? '') }}" placeholder="pisahkan dengan koma">
</div>

<div class="mb-3">
    <label for="meta_descriptions" class="form-label">Meta Description</label>
    <textarea name="meta_descriptions" id="meta_descriptions" class="form-control" rows="2">{{ old('meta_descriptions', $video->meta_descriptions ?? '') }}</textarea>
    <div class="form-text">Kosongkan untuk otomatis memakai Deskripsi di atas.</div>
</div>

<div class="mb-4">
    <label for="meta_images" class="form-label">Meta Image (Open Graph)</label>
    @if (! empty($video) && $video->meta_images)
        <div class="mb-2">
            <img src="{{ $video->meta_images_url }}" alt="Meta image" style="max-width: 200px; max-height: 200px; object-fit: cover;" class="rounded border">
        </div>
    @endif
    <input type="file" name="meta_images" id="meta_images" class="form-control" accept="image/*">
    <div class="form-text">Kosongkan untuk otomatis memakai Thumbnail di atas.</div>
</div>

<hr class="my-4">

<div class="mb-3">
    <label for="date_publish" class="form-label">Tanggal Publish</label>
    <input type="datetime-local" name="date_publish" id="date_publish" class="form-control"
        value="{{ old('date_publish', isset($video) && $video->date_publish ? $video->date_publish->format('Y-m-d\TH:i') : '') }}">
</div>

<div class="mb-3">
    <label for="user_id" class="form-label">User (opsional)</label>
    <select name="user_id" id="user_id" class="form-select">
        <option value="">— Tidak terikat user —</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('user_id', $video->user_id ?? '') == $user->id)>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $video->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $video->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('web.videos.index') }}" class="btn btn-light">Batal</a>
</div>
