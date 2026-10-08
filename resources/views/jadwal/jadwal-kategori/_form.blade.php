<input type="hidden" name="jadwal_mata_pelajaran_id" value="{{ $mataPelajaran->id }}">

<div class="mb-3">
    <label class="form-label">Nama Kategori</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $kategori->name ?? '') }}" placeholder="Misal: Classic Level 1, Pop" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">Harga bulanan & persentase fee pengajar diatur di masing-masing Grade kategori ini.</div>
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $kategori->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $kategori->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
