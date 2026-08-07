@csrf
@if (isset($term))
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
    <label for="name" class="form-label">Judul <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $term->name ?? 'Syarat dan Ketentuan') }}" required>
</div>

<div class="mb-3">
    <label for="descriptions" class="form-label">Isi Syarat & Ketentuan <span class="text-danger">*</span></label>
    <textarea name="descriptions" id="descriptions" class="form-control" rows="14" required>{{ old('descriptions', $term->descriptions ?? '') }}</textarea>
    <div class="form-text">Teks polos (baris baru otomatis ditampilkan sebagai paragraf terpisah di popup Register) — belum mendukung format kaya (bold/list/dsb).</div>
</div>

<div class="mb-3">
    <label for="user_id" class="form-label">User (opsional)</label>
    <select name="user_id" id="user_id" class="form-select">
        <option value="">— Tidak terikat user —</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('user_id', $term->user_id ?? '') == $user->id)>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
</div>

<div class="mb-4">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $term->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $term->status ?? '') === 'inactive')>Inactive</option>
    </select>
    <div class="form-text">Hanya boleh ada 1 versi Active — menyimpan versi ini sebagai Active otomatis menonaktifkan versi Active lainnya.</div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('web.term-conditions.index') }}" class="btn btn-light">Batal</a>
</div>
