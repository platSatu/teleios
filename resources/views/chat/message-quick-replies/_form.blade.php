<div class="mb-3">
    <label class="form-label">Device</label>
    <select name="device_id" class="wa-device-select form-select @error('device_id', $errorBag) is-invalid @enderror"
        data-selected="{{ old('device_id', $quickReply->device_id ?? '') }}" required>
        <option value="">Memuat device...</option>
    </select>
    @error('device_id', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Judul</label>
    <input type="text" name="title" value="{{ old('title', $quickReply->title ?? '') }}"
        class="form-control @error('title', $errorBag) is-invalid @enderror" required>
    @error('title', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Shortcut (opsional)</label>
    <div class="input-group">
        <span class="input-group-text">/</span>
        <input type="text" name="shortcut" value="{{ old('shortcut', $quickReply->shortcut ?? '') }}"
            class="form-control @error('shortcut', $errorBag) is-invalid @enderror" placeholder="harga">
    </div>
    @error('shortcut', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Kategori</label>
    <select name="category" class="form-select @error('category', $errorBag) is-invalid @enderror">
        <option value="text" @selected(old('category', $quickReply->category ?? 'text') == 'text')>Text</option>
        <option value="location" @selected(old('category', $quickReply->category ?? '') == 'location')>Location</option>
    </select>
    @error('category', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Isi Pesan</label>
    <textarea name="message_content" rows="3" class="form-control @error('message_content', $errorBag) is-invalid @enderror" required>{{ old('message_content', $quickReply->message_content ?? '') }}</textarea>
    @error('message_content', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status', $errorBag) is-invalid @enderror">
        <option value="active" @selected(old('status', $quickReply->status ?? 'active') == 'active')>Active</option>
        <option value="inactive" @selected(old('status', $quickReply->status ?? '') == 'inactive')>Inactive</option>
    </select>
    @error('status', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
