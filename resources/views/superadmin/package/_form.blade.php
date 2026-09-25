@csrf
@if (isset($package))
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
    <label for="name" class="form-label">Nama Package <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $package->name ?? '') }}" required>
</div>

<div class="mb-3">
    <label class="form-label">Layanan yang Dicakup <span class="text-danger">*</span></label>
    @php
        $selectedCategoryIds = old(
            'category_application_ids',
            isset($package) ? $package->categoryIds() : []
        );
    @endphp
    <div class="row g-2">
        @foreach ($categoryApplications as $category)
            <div class="col-6 col-md-4">
                <div class="form-check">
                    <input type="checkbox" name="category_application_ids[]" value="{{ $category->id }}"
                        id="category_{{ $category->id }}" class="form-check-input"
                        @checked(in_array($category->id, $selectedCategoryIds, true))>
                    <label for="category_{{ $category->id }}" class="form-check-label">{{ $category->name }}</label>
                </div>
            </div>
        @endforeach
    </div>
    <div class="form-text">
        Centang semua layanan yang termasuk paket ini (mis. paket Lengkap: Chat, Form, Jadwal, Tagihan).
        Nama layanan dipakai untuk membuka menu -- jangan diganti setelah dipakai customer.
    </div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Deskripsi / Fitur</label>
    <textarea name="description" id="description" class="form-control" rows="6"
        placeholder="Auto-reply &amp; chatbot&#10;Form online&#10;Jadwal &amp; pengingat otomatis">{{ old('description', $package->description ?? '') }}</textarea>
    <div class="form-text">
        Satu baris = satu fitur. Tampil sebagai daftar centang di bawah limit paket (dashboard &amp; halaman depan), sesuai urutan yang Anda tulis.
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="duration" class="form-label">Durasi (hari) <span class="text-danger">*</span></label>
        <input type="number" name="duration" id="duration" class="form-control" min="1" placeholder="30" value="{{ old('duration', $package->duration ?? '') }}" required>
    </div>
    <div class="col-md-6 mb-3">
        <label for="price" class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
        <input type="number" name="price" id="price" class="form-control" min="0" step="0.01" value="{{ old('price', $package->price ?? '') }}" required>
    </div>
</div>

<div class="mb-3">
    <label for="user_id" class="form-label">User (opsional)</label>
    <select name="user_id" id="user_id" class="form-select">
        <option value="">— Tidak terikat user —</option>
        @foreach ($users as $user)
            <option value="{{ $user->id }}" @selected(old('user_id', $package->user_id ?? '') == $user->id)>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select" required>
        <option value="active" @selected(old('status', $package->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $package->status ?? '') === 'inactive')>Inactive</option>
    </select>
</div>

<div class="mb-4 form-check">
    <input type="checkbox" name="is_featured" id="is_featured" class="form-check-input" value="1"
        @checked(old('is_featured', $package->is_featured ?? false))>
    <label for="is_featured" class="form-check-label">
        Tandai sebagai "TERPOPULER" di halaman depan (fe-konexa)
    </label>
    <div class="form-text">Boleh lebih dari satu package ditandai sekaligus kalau memang perlu.</div>
</div>

<div class="mb-4 form-check">
    <input type="checkbox" name="is_trial" id="is_trial" class="form-check-input" value="1"
        @checked(old('is_trial', $package->is_trial ?? false))>
    <label for="is_trial" class="form-check-label">Paket trial</label>
    <div class="form-text">
        Hanya bisa diambil sekali per nomor HP owner, dan selama masih aktif boleh langsung diganti paket berbayar.
        Biasanya harga Rp 0 dengan durasi pendek (mis. 3 hari).
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('package.index') }}" class="btn btn-light">Batal</a>
</div>
