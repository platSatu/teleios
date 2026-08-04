@php($suffix = $reminder->id ?? 'new')

<div class="mb-3">
    <label class="form-label">Device</label>
    <select name="device_id" class="wa-device-select form-select @error('device_id', $errorBag) is-invalid @enderror"
        data-selected="{{ old('device_id', $reminder->device_id ?? '') }}" required>
        <option value="">Memuat device...</option>
    </select>
    @error('device_id', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Kategori</label>
    <select name="category_message_reminder" class="form-select @error('category_message_reminder', $errorBag) is-invalid @enderror" required>
        @foreach($categories as $category)
            <option value="{{ $category }}" @selected(old('category_message_reminder', $reminder->category_message_reminder ?? '') == $category)>{{ $category }}</option>
        @endforeach
    </select>
    @error('category_message_reminder', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Judul Pengingat</label>
    <input type="text" name="title_reminder" value="{{ old('title_reminder', $reminder->title_reminder ?? '') }}"
        class="form-control @error('title_reminder', $errorBag) is-invalid @enderror" required>
    @error('title_reminder', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Isi Pesan</label>
    <textarea name="message" rows="3" class="form-control @error('message', $errorBag) is-invalid @enderror" required>{{ old('message', $reminder->message ?? '') }}</textarea>
    @error('message', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-check form-switch mb-3">
    <input class="form-check-input wa-group-toggle" type="checkbox" role="switch" name="is_group" value="1"
        id="isGroupReminder{{ $suffix }}" data-target="#groupFieldReminder{{ $suffix }}" data-alt="#phoneFieldReminder{{ $suffix }}"
        @checked(old('is_group', $reminder->is_group ?? false))>
    <label class="form-check-label" for="isGroupReminder{{ $suffix }}">Kirim ke Grup WhatsApp</label>
</div>

<div class="mb-3" id="groupFieldReminder{{ $suffix }}" style="{{ old('is_group', $reminder->is_group ?? false) ? '' : 'display:none;' }}">
    <label class="form-label">Group JID</label>
    <input type="text" name="group_jid" value="{{ old('group_jid', $reminder->group_jid ?? '') }}"
        class="form-control @error('group_jid', $errorBag) is-invalid @enderror" placeholder="1234567890-1234567890@g.us">
    @error('group_jid', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3" id="phoneFieldReminder{{ $suffix }}" style="{{ old('is_group', $reminder->is_group ?? false) ? 'display:none;' : '' }}">
    <label class="form-label">Nomor WhatsApp Tujuan</label>
    <input type="text" name="phone_number" value="{{ old('phone_number', $reminder->phone_number ?? '') }}"
        class="form-control @error('phone_number', $errorBag) is-invalid @enderror" placeholder="6281234567890">
    @error('phone_number', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Kirim Pada</label>
    <input type="datetime-local" name="start_reminder" value="{{ old('start_reminder', isset($reminder->start_reminder) ? \Illuminate\Support\Carbon::parse($reminder->start_reminder)->format('Y-m-d\TH:i') : '') }}"
        class="form-control @error('start_reminder', $errorBag) is-invalid @enderror" required>
    @error('start_reminder', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status', $errorBag) is-invalid @enderror">
        <option value="active" @selected(old('status', $reminder->status ?? 'active') == 'active')>Active</option>
        <option value="inactive" @selected(old('status', $reminder->status ?? '') == 'inactive')>Inactive</option>
    </select>
    @error('status', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<script>
    (function () {
        var toggle = document.getElementById('isGroupReminder{{ $suffix }}');
        if (!toggle) return;
        toggle.addEventListener('change', function () {
            var groupField = document.querySelector(toggle.getAttribute('data-target'));
            var phoneField = document.querySelector(toggle.getAttribute('data-alt'));
            if (groupField) groupField.style.display = toggle.checked ? '' : 'none';
            if (phoneField) phoneField.style.display = toggle.checked ? 'none' : '';
        });
    })();
</script>
