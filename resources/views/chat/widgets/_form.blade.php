{{-- Form widget (tambah & edit). Variabel: $widget (null = baru), $errorBag, $isOwner, $branchOffices, $lockedBranchOffice --}}
@php
    $val = fn (string $field, $default = null) => old($field, $default);
    $domains = $val('allowed_domains', implode("\n", $widget->allowed_domains ?? []));
@endphp

@if ($errors->getBag($errorBag)->any())
    <div class="alert alert-danger small">{{ $errors->getBag($errorBag)->first() }}</div>
@endif

<div class="mb-3">
    <label class="form-label">Nama widget</label>
    <input type="text" name="name" class="form-control" maxlength="100" required placeholder="mis. Website Toko Saya" value="{{ $val('name', $widget->name ?? '') }}">
</div>

<div class="mb-3">
    <label class="form-label">Domain website</label>
    <textarea name="allowed_domains" class="form-control" rows="2" required placeholder="tokosaya.com">{{ $domains }}</textarea>
    <div class="form-text">Domain website <b>tempat widget dipasang</b> (bukan alamat dashboard ini), mis. <code>konexa.id</code>. Satu domain per baris, subdomain ikut diizinkan. Widget tidak akan tampil di website lain.</div>
</div>

@if ($isOwner)
    <div class="mb-3">
        <label class="form-label">Cabang</label>
        <select name="branch_office_id" class="form-select">
            <option value="">Pusat</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected($val('branch_office_id', $widget->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        <div class="form-text">AI yang menjawab adalah AI Bot milik cabang ini.</div>
    </div>
@else
    <div class="mb-3">
        <label class="form-label">Cabang</label>
        <input type="text" class="form-control" value="{{ $lockedBranchOffice->name ?? 'Pusat' }}" disabled>
    </div>
@endif

<div class="row">
    <div class="col-sm-4 mb-3">
        <label class="form-label">Warna</label>
        <input type="color" name="color" class="form-control form-control-color w-100" value="{{ $val('color', $widget?->setting('color') ?? \App\Models\ChatWidget::DEFAULT_SETTINGS['color']) }}">
    </div>
    <div class="col-sm-4 mb-3">
        <label class="form-label">Posisi</label>
        <select name="position" class="form-select">
            @foreach (['right' => 'Kanan bawah', 'left' => 'Kiri bawah'] as $value => $label)
                <option value="{{ $value }}" @selected($val('position', $widget?->setting('position') ?? 'right') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-4 mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="active" @selected($val('status', $widget->status ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected($val('status', $widget->status ?? 'active') === 'inactive')>Nonaktif</option>
        </select>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Judul</label>
    <input type="text" name="title" class="form-control" maxlength="60" required value="{{ $val('title', $widget?->setting('title') ?? \App\Models\ChatWidget::DEFAULT_SETTINGS['title']) }}">
</div>

<div class="mb-3">
    <label class="form-label">Pesan sapaan</label>
    <textarea name="greeting" class="form-control" rows="2" maxlength="300" required>{{ $val('greeting', $widget?->setting('greeting') ?? \App\Models\ChatWidget::DEFAULT_SETTINGS['greeting']) }}</textarea>
</div>

<div class="form-check form-switch mb-2">
    <input class="form-check-input" type="checkbox" role="switch" name="ai_enabled" value="1" id="cwAi{{ $widget->id ?? 'new' }}" @checked($val('ai_enabled', $widget->ai_enabled ?? true))>
    <label class="form-check-label" for="cwAi{{ $widget->id ?? 'new' }}">AI menjawab otomatis (pakai AI Bot cabang)</label>
</div>
<div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" role="switch" name="require_contact" value="1" id="cwContact{{ $widget->id ?? 'new' }}" @checked($val('require_contact', $widget?->setting('require_contact') ?? false))>
    <label class="form-check-label" for="cwContact{{ $widget->id ?? 'new' }}">Minta nama &amp; no. WhatsApp sebelum chat</label>
</div>
