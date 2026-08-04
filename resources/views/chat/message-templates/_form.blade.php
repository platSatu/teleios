<div class="mb-3">
    <label class="form-label">Nama Template <span class="text-danger">*</span></label>
    <input type="text" name="name" value="{{ old('name', $template->name ?? '') }}"
        class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Promo Akhir Bulan" required autofocus>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Isi Pesan <span class="text-danger">*</span></label>
    <textarea name="template" rows="6" class="form-control @error('template') is-invalid @enderror"
        placeholder="Tulis isi pesan yang ingin disimpan sebagai template...">{{ old('template', $template->template ?? '') }}</textarea>
    <div class="form-text">Template ini akan bisa dipilih langsung saat membuat Pesan Terjadwal.</div>
    @error('template')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-4">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $template->status ?? 'active') == 'active')>Active</option>
        <option value="inactive" @selected(old('status', $template->status ?? '') == 'inactive')>Inactive</option>
    </select>
    <div class="form-text">Hanya template berstatus Active yang muncul di pilihan Pesan Terjadwal.</div>
    @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
