@csrf
@if (isset($provider))
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
    <label for="name" class="form-label">Nama Provider <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" placeholder="mis. OpenAI (ChatGPT)"
        value="{{ old('name', $provider->name ?? '') }}" required>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $provider->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $provider->status ?? '') === 'inactive')>Inactive</option>
    </select>
    <div class="form-text">Inactive = tidak muncul lagi di dropdown company mana pun, tapi konfigurasi AI Bot yang sudah memakainya tetap tersimpan.</div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('wa-ai-bot-provider.index') }}" class="btn btn-light">Batal</a>
</div>
