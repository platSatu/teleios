<div class="mb-3">
    <label class="form-label">Nama Form</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $header->name ?? '') }}" placeholder="Misal: Pendaftaran Kelas Piano Batch 3" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if (isset($header) && $header)
        <div class="form-text">
            URL publik: <a href="{{ route('form.public.show', $header->slug) }}" target="_blank">{{ url('/'.$header->slug) }}</a>
            — slug tidak berubah walau nama form diedit.
        </div>
    @else
        <div class="form-text">URL publik (slug) akan dibuat otomatis dari nama form ini saat disimpan.</div>
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Deskripsi (opsional)</label>
    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
        placeholder="Deskripsi singkat yang tampil di atas form...">{{ old('description', $header->description ?? '') }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Gambar Latar (opsional)</label>
    @if (($header->background_url ?? null))
        <div class="mb-2 d-flex align-items-center gap-2">
            <img src="{{ $header->background_url }}" alt="" class="rounded" style="width: 64px; height: 64px; object-fit: cover;">
            <div class="form-check">
                <input type="checkbox" name="remove_background" id="remove_background" value="1" class="form-check-input">
                <label for="remove_background" class="form-check-label">Hapus gambar saat ini</label>
            </div>
        </div>
    @endif
    <input type="file" name="background" accept=".jpg,.jpeg,.png" class="form-control @error('background') is-invalid @enderror">
    @error('background')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">Format jpg/jpeg/png, maksimal 2MB. Tampil sebagai banner di atas form publik.</div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Mulai</label>
        <input type="datetime-local" name="start_date" class="form-control @error('start_date') is-invalid @enderror"
            value="{{ old('start_date', isset($header) && $header?->start_date ? $header->start_date->format('Y-m-d\TH:i') : '') }}" required>
        @error('start_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label">Selesai</label>
        <input type="datetime-local" name="end_date" class="form-control @error('end_date') is-invalid @enderror"
            value="{{ old('end_date', isset($header) && $header?->end_date ? $header->end_date->format('Y-m-d\TH:i') : '') }}" required>
        @error('end_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
<div class="form-text mb-3">Form publik hanya bisa diisi dalam rentang waktu ini, dan hanya kalau statusnya Active.</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $header->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $header->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
