@csrf
@if (isset($packageLimit))
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
    <label for="package_id" class="form-label">Package <span class="text-danger">*</span></label>
    <select name="package_id" id="package_id" class="form-select" required>
        <option value="">— Pilih package —</option>
        @foreach ($packages as $package)
            <option value="{{ $package->id }}" @selected(old('package_id', $packageLimit->package_id ?? '') == $package->id)>
                {{ $package->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="limit_metric_id" class="form-label">Limit Metric <span class="text-danger">*</span></label>
    <select name="limit_metric_id" id="limit_metric_id" class="form-select" required>
        <option value="">— Pilih metric —</option>
        @foreach ($limitMetrics as $metric)
            <option value="{{ $metric->id }}" @selected(old('limit_metric_id', $packageLimit->limit_metric_id ?? '') == $metric->id)>
                {{ $metric->name }} @if ($metric->unit) ({{ $metric->unit }}) @endif
            </option>
        @endforeach
    </select>
</div>

<div class="mb-4">
    <label for="max_value" class="form-label">Batas Maksimal <span class="text-danger">*</span></label>
    <input type="number" name="max_value" id="max_value" class="form-control" min="1" placeholder="10000"
        value="{{ old('max_value', $packageLimit->max_value ?? '') }}" required>
    <div class="form-text">Untuk metric bertipe "Consumable", ini adalah kuota per periode subscription. Untuk "Stock", ini adalah batas jumlah data yang boleh ada sekaligus.</div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('package-limit.index') }}" class="btn btn-light">Batal</a>
</div>
