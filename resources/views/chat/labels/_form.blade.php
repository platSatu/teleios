@if ($errors->getBag($errorBag)->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->getBag($errorBag)->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-3">
    <label class="form-label">Nama Label <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control" placeholder="mis. Prospek, VIP, Sudah Bayar"
        value="{{ old('name', $label->name ?? '') }}" required maxlength="100">
</div>

<div class="mb-3">
    <label class="form-label">Warna</label>
    <input type="color" name="color" class="form-control form-control-color"
        value="{{ old('color', $label->color ?? '#6b7280') }}" title="Pilih warna label">
</div>
