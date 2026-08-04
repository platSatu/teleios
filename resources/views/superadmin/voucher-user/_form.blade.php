@csrf
@if (isset($voucherUser))
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
    <label for="name" class="form-label">Nama Voucher <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $voucherUser->name ?? '') }}" required>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="kode_voucher" class="form-label">Kode Voucher</label>
        <input type="text" inputmode="numeric" maxlength="6" name="kode_voucher" id="kode_voucher" class="form-control"
            value="{{ old('kode_voucher', $voucherUser->kode_voucher ?? '') }}" placeholder="Kosongkan untuk generate otomatis">
        <div class="form-text">6 digit angka, unik. Kosongkan supaya dibuat otomatis oleh sistem.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label for="percentase" class="form-label">Persentase Diskon (%) <span class="text-danger">*</span></label>
        <input type="number" step="0.01" min="0" max="100" name="percentase" id="percentase" class="form-control"
            value="{{ old('percentase', $voucherUser->percentase ?? '') }}" required>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="limit" class="form-label">Limit Total Pemakaian <span class="text-danger">*</span></label>
        <input type="number" min="1" name="limit" id="limit" class="form-control"
            value="{{ old('limit', $voucherUser->limit ?? '') }}" required>
        <div class="form-text">Total berapa kali kode ini boleh dipakai, gabungan semua user.</div>
    </div>
    <div class="col-md-6 mb-3">
        <label for="use_by_user" class="form-label">Batas Pemakaian per User <span class="text-danger">*</span></label>
        <input type="number" min="1" name="use_by_user" id="use_by_user" class="form-control"
            value="{{ old('use_by_user', $voucherUser->use_by_user ?? 1) }}" required>
        <div class="form-text">Berapa kali SATU user boleh memakai kode ini.</div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="valid_from" class="form-label">Berlaku Dari <span class="text-danger">*</span></label>
        {{-- datetime-local (not date) — valid_from/valid_until are real
             datetime columns now, and expiry is checked down to the
             minute (see PackageCheckoutController::validatePromo), so a
             plain date input would silently pin every manual save back
             to midnight. --}}
        <input type="datetime-local" name="valid_from" id="valid_from" class="form-control"
            value="{{ old('valid_from', isset($voucherUser) ? $voucherUser->valid_from?->format('Y-m-d\TH:i') : '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label for="valid_until" class="form-label">Berlaku Sampai <span class="text-danger">*</span></label>
        <input type="datetime-local" name="valid_until" id="valid_until" class="form-control"
            value="{{ old('valid_until', isset($voucherUser) ? $voucherUser->valid_until?->format('Y-m-d\TH:i') : '') }}" required>
    </div>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        @foreach (['active' => 'Active', 'expire' => 'Expire', 'used' => 'Used', 'inactive' => 'Inactive'] as $value => $label)
            <option value="{{ $value }}" @selected(old('status', $voucherUser->status ?? 'active') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('voucher-user.index') }}" class="btn btn-light">Batal</a>
</div>
