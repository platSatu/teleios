@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-1">Moderasi AI</h4>
                    <p class="text-muted mb-4">
                        AI ini memeriksa (dan bila memungkinkan, memperbaiki otomatis) setiap Kategori Template dan
                        WA Template yang dibuat perusahaan pengguna — menggantikan approval manual superadmin.
                        Tanamkan API key salah satu provider di bawah lalu aktifkan.
                    </p>

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('ai-moderation-setting.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">AI Provider</label>
                                <select name="wa_ai_bot_provider_id" class="ai-mod-provider-select form-select"
                                    data-target="#aiModModel" data-selected-model="{{ old('wa_ai_bot_model_id', $setting->wa_ai_bot_model_id ?? '') }}">
                                    <option value="">-- Pilih Provider --</option>
                                    @foreach ($providers as $provider)
                                        <option value="{{ $provider->id }}" @selected(old('wa_ai_bot_provider_id', $setting->wa_ai_bot_provider_id) == $provider->id)>{{ $provider->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Katalog sama dengan Provider AI (AI Bot) — tambah/nonaktifkan provider di menu itu.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">AI Model</label>
                                <select name="wa_ai_bot_model_id" id="aiModModel" class="form-select">
                                    <option value="">-- Pilih Provider dulu --</option>
                                    @if ($setting->model)
                                        <option value="{{ $setting->model->id }}" selected>{{ $setting->model->name }}</option>
                                    @endif
                                </select>
                            </div>
                        </div>

                        <script id="aiModCatalog" type="application/json">
                            {!! $providers->map(fn ($provider) => [
                                'id' => $provider->id,
                                'name' => $provider->name,
                                'models' => $provider->models->map(fn ($model) => ['id' => $model->id, 'name' => $model->name])->values(),
                            ])->values()->toJson() !!}
                        </script>

                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <input type="password" name="api_key" class="form-control" autocomplete="new-password"
                                placeholder="{{ $setting->api_key ? 'Sudah tersimpan — isi untuk mengganti' : 'Belum diisi' }}">
                            <div class="form-text">Disimpan terenkripsi. Kosongkan kalau tidak ingin mengganti key yang sudah ada.</div>
                        </div>

                        <label class="form-label d-block">Kategori yang Diblokir</label>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="block_pornography" value="1" id="blockPornography" class="form-check-input" @checked(old('block_pornography', $setting->block_pornography))>
                                    <label for="blockPornography" class="form-check-label">Pornografi</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="block_gambling" value="1" id="blockGambling" class="form-check-input" @checked(old('block_gambling', $setting->block_gambling))>
                                    <label for="blockGambling" class="form-check-label">Judi</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input type="checkbox" name="block_drugs" value="1" id="blockDrugs" class="form-check-input" @checked(old('block_drugs', $setting->block_drugs))>
                                    <label for="blockDrugs" class="form-check-label">Narkoba</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="block_negative_language" value="1" id="blockNegative" class="form-check-input" @checked(old('block_negative_language', $setting->block_negative_language))>
                                    <label for="blockNegative" class="form-check-label">Kalimat negatif / kata kasar lainnya</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kata Terlarang Tambahan (opsional)</label>
                            <textarea name="blocked_keywords" class="form-control" rows="2" placeholder="Pisahkan dengan koma atau baris baru">{{ old('blocked_keywords', $setting->blocked_keywords) }}</textarea>
                            <div class="form-text">Dicek langsung tanpa AI — kalau ada kata ini di teks, otomatis ditolak.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Instruksi Tambahan untuk AI (opsional)</label>
                            <textarea name="custom_instructions" class="form-control" rows="2" placeholder="mis. jangan izinkan klaim medis berlebihan">{{ old('custom_instructions', $setting->custom_instructions) }}</textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" @selected(old('status', $setting->status) === 'active')>Aktif</option>
                                <option value="inactive" @selected(old('status', $setting->status) === 'inactive')>Nonaktif</option>
                            </select>
                            <div class="form-text">Nonaktif = template baru/edit tetap tersimpan tapi statusnya ditahan (pending), tidak diperiksa AI.</div>
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var providerSelect = document.querySelector('select[name="wa_ai_bot_provider_id"][data-target="#aiModModel"]');
            var catalogScript = document.getElementById('aiModCatalog');
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
@endsection
