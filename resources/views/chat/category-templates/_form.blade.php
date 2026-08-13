<div class="mb-3">
    <label class="form-label">Nama Kategori <span class="text-danger">*</span></label>
    <input type="text" name="name" value="{{ old('name', $category->name ?? '') }}"
        class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Promo, Reminder, Info Produk" required autofocus>
    <div class="form-text">Nama diperiksa AI moderasi begitu disimpan — otomatis lolos, disesuaikan, atau ditolak.</div>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@if (isset($category))
    <div class="mb-4">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active" @selected(old('status', $category->status) == 'active')>Active</option>
            <option value="inactive" @selected(old('status', $category->status) == 'inactive')>Inactive</option>
        </select>
        <div class="form-text">Hanya kategori Active &amp; sudah lolos moderasi AI yang muncul di form WA Template.</div>
    </div>
@endif
