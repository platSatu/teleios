@csrf
@if (isset($model))
    @method('PUT')
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

<div class="mb-3">
    <label for="wa_ai_bot_provider_id" class="form-label">Provider <span class="text-danger">*</span></label>
    <select name="wa_ai_bot_provider_id" id="wa_ai_bot_provider_id" class="form-select" required>
        <option value="">-- Pilih Provider --</option>
        @foreach ($providers as $provider)
            <option value="{{ $provider->id }}" @selected(old('wa_ai_bot_provider_id', $model->wa_ai_bot_provider_id ?? '') == $provider->id)>
                {{ $provider->name }}
            </option>
        @endforeach
    </select>
    <div class="form-text">Belum ada provider yang cocok? Tambah dulu di <a href="{{ route('wa-ai-bot-provider.create') }}">Provider AI</a>.</div>
</div>

<div class="mb-3">
    <label for="name" class="form-label">Nama Model <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" placeholder="mis. gpt-4o-mini"
        value="{{ old('name', $model->name ?? '') }}" required>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $model->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $model->status ?? '') === 'inactive')>Inactive</option>
    </select>
    <div class="form-text">Inactive = tidak muncul lagi di dropdown Model, walau providernya masih Active.</div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('wa-ai-bot-model.index') }}" class="btn btn-light">Batal</a>
</div>
