@php($suffix = $autoReply->id ?? 'new')

<div class="mb-3">
    <label class="form-label">Device</label>
    <select name="device_id" class="wa-device-select form-select @error('device_id', $errorBag) is-invalid @enderror"
        data-selected="{{ old('device_id', $autoReply->device_id ?? '') }}" required>
        <option value="">Memuat device...</option>
    </select>
    @error('device_id', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" role="switch" name="is_default" value="1"
        id="isDefault{{ $suffix }}" data-target="#keywordFields{{ $suffix }}"
        @checked(old('is_default', $autoReply->is_default ?? false))>
    <label class="form-check-label" for="isDefault{{ $suffix }}">
        Jadikan Balasan Default
    </label>
    <div class="form-text">Dikirim otomatis kalau pesan masuk tidak cocok kata kunci manapun — cocok untuk menu pembuka, misal "Ketik 1 untuk Jadwal, 2 untuk Pembayaran, 3 untuk Daftar User".</div>
</div>

<div id="keywordFields{{ $suffix }}" style="{{ old('is_default', $autoReply->is_default ?? false) ? 'display:none;' : '' }}">
    <div class="mb-3">
        <label class="form-label">Kata Kunci</label>
        <input type="text" name="keyword" value="{{ old('keyword', $autoReply->keyword ?? '') }}"
            class="form-control @error('keyword', $errorBag) is-invalid @enderror" placeholder="harga, atau cukup angka: 1">
        @error('keyword', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label">Cara Mencocokkan</label>
        <select name="match_type" class="form-select @error('match_type', $errorBag) is-invalid @enderror">
            <option value="contains" @selected(old('match_type', $autoReply->match_type ?? 'contains') == 'contains')>Mengandung kata kunci</option>
            <option value="exact" @selected(old('match_type', $autoReply->match_type ?? '') == 'exact')>Sama persis dengan pesan</option>
        </select>
        <div class="form-text">Untuk menu bernomor (1/2/3), pilih "Sama persis" supaya tidak salah kena pesan lain yang cuma kebetulan mengandung angka itu.</div>
        @error('match_type', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-2">
    <label class="form-label">Pesan Balasan</label>
    <textarea name="reply_message" id="replyMessage{{ $suffix }}" rows="4" class="form-control @error('reply_message', $errorBag) is-invalid @enderror" required>{{ old('reply_message', $autoReply->reply_message ?? '') }}</textarea>
    @error('reply_message', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <div class="form-text mb-1">Klik untuk sisipkan data otomatis (selalu data terbaru saat pesan dikirim):</div>
    <div class="d-flex flex-wrap gap-1">
        @foreach(\App\Services\Chat\AutoReplyTagResolver::availableTags() as $tag => $description)
            <button type="button" class="btn btn-sm btn-outline-secondary insert-tag-btn" data-tag="{{ $tag }}" data-target="#replyMessage{{ $suffix }}" title="{{ $description }}">
                <code>{{ $tag }}</code>
            </button>
        @endforeach
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status', $errorBag) is-invalid @enderror">
        <option value="active" @selected(old('status', $autoReply->status ?? 'active') == 'active')>Active</option>
        <option value="inactive" @selected(old('status', $autoReply->status ?? '') == 'inactive')>Inactive</option>
    </select>
    @error('status', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<script>
    (function () {
        var toggle = document.getElementById('isDefault{{ $suffix }}');
        if (toggle) {
            toggle.addEventListener('change', function () {
                var target = document.querySelector(toggle.getAttribute('data-target'));
                if (target) target.style.display = toggle.checked ? 'none' : '';
            });
        }

        document.querySelectorAll('.insert-tag-btn[data-target="#replyMessage{{ $suffix }}"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var textarea = document.querySelector(btn.getAttribute('data-target'));
                if (!textarea) return;
                var tag = btn.getAttribute('data-tag');
                var start = textarea.selectionStart ?? textarea.value.length;
                var end = textarea.selectionEnd ?? textarea.value.length;
                textarea.value = textarea.value.slice(0, start) + tag + textarea.value.slice(end);
                textarea.focus();
                textarea.selectionStart = textarea.selectionEnd = start + tag.length;
            });
        });
    })();
</script>
