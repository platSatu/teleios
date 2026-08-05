@php($suffix = $bot->id ?? 'new')
@php($selectedProviderId = old('wa_ai_bot_provider_id', $bot->wa_ai_bot_provider_id ?? ''))
@php($selectedModelId = old('wa_ai_bot_model_id', $bot->wa_ai_bot_model_id ?? ''))

<div class="mb-3">
    <label class="form-label">Device (hanya yang sedang terhubung)</label>
    <select name="device_id" class="wa-device-select form-select @error('device_id', $errorBag) is-invalid @enderror"
        data-selected="{{ old('device_id', $bot->device_id ?? '') }}" data-only-connected="1" required>
        <option value="">Memuat device...</option>
    </select>
    @error('device_id', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@if ($isOwner)
    <div class="mb-3">
        <label class="form-label">Cabang</label>
        <select name="branch_office_id" class="form-select @error('branch_office_id', $errorBag) is-invalid @enderror">
            <option value="">-- Semua / Belum ditentukan --</option>
            @foreach($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_office_id', $bot->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_office_id', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
@else
    <input type="hidden" name="branch_office_id" value="{{ $lockedBranchOffice?->id }}">
    <div class="mb-3">
        <label class="form-label">Cabang</label>
        <input type="text" class="form-control" value="{{ $lockedBranchOffice->name ?? '-' }}" disabled>
    </div>
@endif

<div class="row">
    <div class="col-6 mb-3">
        <label class="form-label">AI Provider</label>
        <select name="wa_ai_bot_provider_id" class="ai-bot-provider-select form-select @error('wa_ai_bot_provider_id', $errorBag) is-invalid @enderror"
            data-target="#aiBotModel{{ $suffix }}" data-selected-model="{{ $selectedModelId }}" required>
            <option value="">-- Pilih Provider --</option>
            @foreach($providers as $provider)
                <option value="{{ $provider->id }}" @selected($selectedProviderId == $provider->id)>{{ $provider->name }}</option>
            @endforeach
        </select>
        @error('wa_ai_bot_provider_id', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 mb-3">
        <label class="form-label">AI Model</label>
        <select name="wa_ai_bot_model_id" id="aiBotModel{{ $suffix }}" class="form-select @error('wa_ai_bot_model_id', $errorBag) is-invalid @enderror" required>
            <option value="">-- Pilih Provider dulu --</option>
            @if ($bot && $bot->model)
                <option value="{{ $bot->model->id }}" selected>{{ $bot->model->name }}</option>
            @endif
        </select>
        @error('wa_ai_bot_model_id', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<script id="aiBotCatalog{{ $suffix }}" type="application/json">
    {!! $providers->map(fn ($provider) => [
        'id' => $provider->id,
        'name' => $provider->name,
        'models' => $provider->models->map(fn ($model) => ['id' => $model->id, 'name' => $model->name])->values(),
    ])->values()->toJson() !!}
</script>

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

<div class="row" id="activationTimeField{{ $suffix }}" style="{{ old('custom_activation_time', $bot->custom_activation_time ?? false) ? '' : 'display:none;' }}">
    <div class="col-6 mb-3">
        <label class="form-label">Aktif Mulai</label>
        <input type="datetime-local" name="activation_start_at" value="{{ old('activation_start_at', isset($bot->activation_start_at) && $bot->activation_start_at ? $bot->activation_start_at->format('Y-m-d\TH:i') : '') }}"
            class="form-control @error('activation_start_at', $errorBag) is-invalid @enderror">
        @error('activation_start_at', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-6 mb-3">
        <label class="form-label">Aktif Sampai</label>
        <input type="datetime-local" name="activation_end_at" value="{{ old('activation_end_at', isset($bot->activation_end_at) && $bot->activation_end_at ? $bot->activation_end_at->format('Y-m-d\TH:i') : '') }}"
            class="form-control @error('activation_end_at', $errorBag) is-invalid @enderror">
        @error('activation_end_at', $errorBag)<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
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

    (function () {
        var providerSelect = document.querySelector('select[name="wa_ai_bot_provider_id"][data-target="#aiBotModel{{ $suffix }}"]');
        var catalogScript = document.getElementById('aiBotCatalog{{ $suffix }}');
        if (!providerSelect || !catalogScript) return;

        var catalog = JSON.parse(catalogScript.textContent || '[]');

        function renderModels(providerId, preselectModelId) {
            var modelSelect = document.querySelector(providerSelect.getAttribute('data-target'));
            if (!modelSelect) return;

            var provider = catalog.find(function (p) { return p.id === providerId; });
            modelSelect.innerHTML = '';

            if (!provider) {
                modelSelect.innerHTML = '<option value="">-- Pilih Provider dulu --</option>';
                return;
            }

            modelSelect.innerHTML = '<option value="">-- Pilih Model --</option>';
            provider.models.forEach(function (model) {
                var option = document.createElement('option');
                option.value = model.id;
                option.textContent = model.name;
                if (preselectModelId && model.id === preselectModelId) {
                    option.selected = true;
                }
                modelSelect.appendChild(option);
            });
        }

        providerSelect.addEventListener('change', function () {
            renderModels(providerSelect.value, null);
        });

        if (providerSelect.value) {
            renderModels(providerSelect.value, providerSelect.getAttribute('data-selected-model'));
        }
    })();
</script>
