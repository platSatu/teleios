@csrf
@if (isset($limitMetric))
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
    <label for="key" class="form-label">Key <span class="text-danger">*</span></label>
    <input type="text" name="key" id="key" class="form-control" placeholder="broadcast_send" pattern="[a-z0-9_]+"
        value="{{ old('key', $limitMetric->key ?? '') }}" required>
    <div class="form-text">Huruf kecil, angka, underscore saja — inilah yang dirujuk kode saat mengecek/menambah kuota (mis. "broadcast_send", "contact_count", "device_count").</div>
</div>

<div class="mb-3">
    <label for="name" class="form-label">Nama <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" placeholder="Pengiriman Broadcast"
        value="{{ old('name', $limitMetric->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="category_application_id" class="form-label">Aplikasi (opsional)</label>
    <select name="category_application_id" id="category_application_id" class="form-select">
        <option value="">— Global, bisa dipakai aplikasi mana pun —</option>
        @foreach ($categoryApplications as $category)
            <option value="{{ $category->id }}" @selected(old('category_application_id', $limitMetric->category_application_id ?? '') == $category->id)>
                {{ $category->name }}
            </option>
        @endforeach
    </select>
    <div class="form-text">Kosongkan agar metric ini bisa dipakai ulang oleh package aplikasi lain di kemudian hari, bukan cuma satu aplikasi.</div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="metric_type" class="form-label">Tipe <span class="text-danger">*</span></label>
        <select name="metric_type" id="metric_type" class="form-select" required>
            <option value="consumable" @selected(old('metric_type', $limitMetric->metric_type ?? 'consumable') === 'consumable')>Consumable (kuota terpakai, reset per subscription)</option>
            <option value="stock" @selected(old('metric_type', $limitMetric->metric_type ?? '') === 'stock')>Stock (jumlah data saat ini, mis. kontak/device)</option>
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label for="unit" class="form-label">Satuan</label>
        <input type="text" name="unit" id="unit" class="form-control" placeholder="pesan / kontak / device"
            value="{{ old('unit', $limitMetric->unit ?? '') }}">
    </div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi</label>
    <textarea name="description" id="description" class="form-control" rows="3">{{ old('description', $limitMetric->description ?? '') }}</textarea>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $limitMetric->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $limitMetric->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('limit-metric.index') }}" class="btn btn-light">Batal</a>
</div>
