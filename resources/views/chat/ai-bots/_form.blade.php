@php($suffix = $bot->id ?? 'new')

<div class="mb-3">
    <label class="form-label">Device (hanya yang sedang terhubung)</label>
    <select name="device_id" class="wa-device-select form-select @error('device_id', $errorBag) is-invalid @enderror"
        data-selected="{{ old('device_id', $bot->device_id ?? '') }}" data-only-connected="1" required>
        <option value="">Memuat device...</option>
    </select>
    @error('device_id', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row">
    <div class="col-6 mb-3">
        <label class="form-label">AI Provider</label>
        <select name="ai_provider" class="form-select @error('ai_provider', $errorBag) is-invalid @enderror" required>
            @foreach($providers as $provider)
                <option value="{{ $provider }}" @selected(old('ai_provider', $bot->ai_provider ?? '') == $provider)>{{ $provider }}</option>
            @endforeach
        </select>
        @error('ai_provider', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 mb-3">
        <label class="form-label">AI Model</label>
        <input type="text" name="ai_model" value="{{ old('ai_model', $bot->ai_model ?? '') }}"
            class="form-control @error('ai_model', $errorBag) is-invalid @enderror" placeholder="mis. gpt-4o-mini">
        @error('ai_model', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Lampiran Knowledge Base (opsional)</label>
    <input type="file" name="attach_file" class="form-control @error('attach_file', $errorBag) is-invalid @enderror">
    @if(($bot->attach_file_original_name ?? null))
        <div class="form-text">File saat ini: {{ $bot->attach_file_original_name }} — upload file baru untuk mengganti.</div>
    @endif
    @error('attach_file', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">API Configuration</label>
    <textarea name="api_configuration" rows="2" class="form-control @error('api_configuration', $errorBag) is-invalid @enderror" placeholder="API key / konfigurasi provider">{{ old('api_configuration', $bot->api_configuration ?? '') }}</textarea>
    <div class="form-text">Disimpan terenkripsi.</div>
    @error('api_configuration', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">AI Behaviour Prompt</label>
    <textarea name="ai_behaviour_prompt" rows="4" class="form-control @error('ai_behaviour_prompt', $errorBag) is-invalid @enderror" placeholder="Instruksi gaya bicara / perilaku bot">{{ old('ai_behaviour_prompt', $bot->ai_behaviour_prompt ?? '') }}</textarea>
    @error('ai_behaviour_prompt', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" role="switch" name="active_bot_immediately" value="1"
        id="activeImmediately{{ $suffix }}" @checked(old('active_bot_immediately', $bot->active_bot_immediately ?? false))>
    <label class="form-check-label" for="activeImmediately{{ $suffix }}">Aktifkan bot segera setelah disimpan</label>
</div>

<div class="form-check form-switch mb-3">
    <input class="form-check-input" type="checkbox" role="switch" name="custom_activation_time" value="1"
        id="customActivation{{ $suffix }}" data-target="#activationTimeField{{ $suffix }}"
        @checked(old('custom_activation_time', $bot->custom_activation_time ?? false))>
    <label class="form-check-label" for="customActivation{{ $suffix }}">Jadwalkan waktu aktivasi custom</label>
</div>

<div class="mb-3" id="activationTimeField{{ $suffix }}" style="{{ old('custom_activation_time', $bot->custom_activation_time ?? false) ? '' : 'display:none;' }}">
    <label class="form-label">Aktif Mulai</label>
    <input type="datetime-local" name="activation_start_at" value="{{ old('activation_start_at', isset($bot->activation_start_at) && $bot->activation_start_at ? $bot->activation_start_at->format('Y-m-d\TH:i') : '') }}"
        class="form-control @error('activation_start_at', $errorBag) is-invalid @enderror">
    @error('activation_start_at', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status', $errorBag) is-invalid @enderror">
        <option value="active" @selected(old('status', $bot->status ?? 'inactive') == 'active')>Active</option>
        <option value="inactive" @selected(old('status', $bot->status ?? 'inactive') == 'inactive')>Inactive</option>
    </select>
    @error('status', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<script>
    (function () {
        var toggle = document.getElementById('customActivation{{ $suffix }}');
        if (!toggle) return;
        toggle.addEventListener('change', function () {
            var field = document.querySelector(toggle.getAttribute('data-target'));
            if (field) field.style.display = toggle.checked ? '' : 'none';
        });
    })();
</script>
