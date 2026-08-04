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
    <label for="category_documentation_id" class="form-label">Kategori <span class="text-danger">*</span></label>
    <select name="category_documentation_id" id="category_documentation_id" class="form-select" required>
        <option value="">-- Pilih Kategori --</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('category_documentation_id', $article->category_documentation_id ?? '') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
    @if ($categories->isEmpty())
        <div class="form-text text-danger">Belum ada kategori — buat dulu di <a href="{{ route('wa-api-dokumentasi.categories.create') }}">Kategori Dokumentasi</a>.</div>
    @endif
</div>

<div class="mb-3">
    <label for="title" class="form-label">Judul <span class="text-danger">*</span></label>
    <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $article->title ?? '') }}" placeholder="Kirim Pesan" required>
</div>

<div class="row">
    <div class="col-md-3 mb-3">
        <label for="method" class="form-label">Method <span class="text-danger">*</span></label>
        <select name="method" id="method" class="form-select" required>
            @foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $m)
                <option value="{{ $m }}" @selected(old('method', $article->method ?? 'POST') === $m)>{{ $m }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-9 mb-3">
        <label for="endpoint" class="form-label">Endpoint <span class="text-danger">*</span></label>
        <input type="text" name="endpoint" id="endpoint" class="form-control font-monospace" value="{{ old('endpoint', $article->endpoint ?? '') }}" placeholder="/api/wa-api/v1/send-message" required>
    </div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $article->description ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="request_example" class="form-label">Contoh Request</label>
    <textarea name="request_example" id="request_example" class="form-control font-monospace" rows="6" placeholder='curl -X POST ... -H "X-WA-Token: ..." -H "X-WA-Secret: ..." -d {"to":"6281234567890","message":"Halo"}'>{{ old('request_example', $article->request_example ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="response_example" class="form-label">Contoh Response</label>
    <textarea name="response_example" id="response_example" class="form-control font-monospace" rows="6" placeholder='{"status":"sent","message":{...}}'>{{ old('response_example', $article->response_example ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="sort_order" class="form-label">Urutan</label>
    <input type="number" name="sort_order" id="sort_order" class="form-control" min="0" value="{{ old('sort_order', $article->sort_order ?? 0) }}">
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
    <a href="{{ route('wa-api-dokumentasi.articles.index') }}" class="btn btn-light">Batal</a>
</div>
