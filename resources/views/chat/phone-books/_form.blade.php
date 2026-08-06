<div class="mb-3">
    <label class="form-label">Nama</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $phoneBook->name ?? '') }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Nomor Telepon</label>
    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
        value="{{ old('phone', $phoneBook->phone ?? '') }}" placeholder="6281234567890" required>
    @error('phone')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Email (opsional)</label>
    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
        value="{{ old('email', $phoneBook->email ?? '') }}">
    @error('email')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Kelompok</label>
    <select name="wa_category_phone_book_id" class="form-select @error('wa_category_phone_book_id') is-invalid @enderror" required>
        <option value="">- Pilih Kelompok -</option>
        @foreach ($categories as $category)
            <option value="{{ $category->id }}" @selected(old('wa_category_phone_book_id', $phoneBook->wa_category_phone_book_id ?? '') == $category->id)>{{ $category->name }}</option>
        @endforeach
    </select>
    @error('wa_category_phone_book_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @if ($categories->isEmpty())
        <div class="form-text text-warning">Belum ada Kelompok — <a href="{{ route('chat.category-phone-books.create') }}">buat Kelompok dulu</a>.</div>
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Branch (opsional)</label>
    @if ($branchOffices->count() <= 1 && $branchOffices->isNotEmpty())
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name }}" disabled>
        <div class="form-text">Kontak ini otomatis terkunci ke branch Anda.</div>
    @else
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror">
            <option value="">- Semua Branch -</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_office_id', $phoneBook->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_office_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $phoneBook->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $phoneBook->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
