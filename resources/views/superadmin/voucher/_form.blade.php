@csrf
@if (isset($voucher))
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
    <label for="kode_voucher" class="form-label">Kode Voucher <span class="text-danger">*</span></label>
    <input type="text" name="kode_voucher" id="kode_voucher" class="form-control" value="{{ old('kode_voucher', $voucher->kode_voucher ?? '') }}" required>
</div>

<div class="mb-3">
    <label for="user_id" class="form-label">User <span class="text-danger">*</span></label>
    <select name="user_id" id="user_id" class="form-select" required>
        <option value="">— Pilih user —</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('user_id', $voucher->user_id ?? '') == $user->id)>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="valid_from" class="form-label">Berlaku Dari <span class="text-danger">*</span></label>
        {{-- datetime-local (not date) — valid_from/valid_until are real
             datetime columns now, and expiry is checked down to the
             minute (see EnsureActivePackage), so a plain date input
             would silently pin every manual save back to midnight. --}}
        <input type="datetime-local" name="valid_from" id="valid_from" class="form-control"
            value="{{ old('valid_from', isset($voucher) ? $voucher->valid_from?->format('Y-m-d\TH:i') : '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label for="valid_until" class="form-label">Berlaku Sampai <span class="text-danger">*</span></label>
        <input type="datetime-local" name="valid_until" id="valid_until" class="form-control"
            value="{{ old('valid_until', isset($voucher) ? $voucher->valid_until?->format('Y-m-d\TH:i') : '') }}" required>
    </div>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $voucher->status ?? 'active') === 'active')>Active</option>
        <option value="pending" @selected(old('status', $voucher->status ?? '') === 'pending')>Pending (belum di-redeem)</option>
        <option value="inactive" @selected(old('status', $voucher->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('voucher.index') }}" class="btn btn-light">Batal</a>
</div>
