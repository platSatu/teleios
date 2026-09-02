<div class="mb-3">
    <label class="form-label">Isi Footer</label>
    <textarea name="name" rows="4" class="form-control @error('name') is-invalid @enderror"
        placeholder="Misal: Terima kasih sudah mengisi form ini. Tim kami akan menghubungi Anda dalam 1x24 jam." required>{{ old('name', $footer->name ?? '') }}</textarea>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $footer->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $footer->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">Hanya Footer berstatus Active yang tampil di form publik.</div>
</div>
