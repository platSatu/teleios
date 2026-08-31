<div class="mb-3">
    <label class="form-label">Nama</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $mataPelajaran->name ?? '') }}" placeholder="Misal: Piano, Bahasa Inggris, Vokal" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Deskripsi (opsional)</label>
    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
        placeholder="Deskripsi singkat bidang ini...">{{ old('description', $mataPelajaran->description ?? '') }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Gambar (opsional)</label>
    @if (($mataPelajaran->image_url ?? null))
        <div class="mb-2 d-flex align-items-center gap-2">
            <img src="{{ $mataPelajaran->image_url }}" alt="" class="rounded" style="width: 64px; height: 64px; object-fit: cover;">
            <div class="form-check">
                <input type="checkbox" name="remove_image" id="remove_image" value="1" class="form-check-input">
                <label for="remove_image" class="form-check-label">Hapus gambar saat ini</label>
            </div>
        </div>
    @endif
    <input type="file" name="image" accept="image/*" class="form-control @error('image') is-invalid @enderror">
    @error('image')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">Format jpg/jpeg/png/webp, maksimal 2MB.</div>
</div>

@php
    // Locked-vs-free (pola "ina" project's University Album create()):
    // kalau create() dibuka dengan ?branch_office_id=... (tombol "+ Add
    // Mata Pelajaran / Bidang" di index Branch), branch dikunci. Tanpa
    // itu -- atau saat edit(), yang tidak pernah mengirim
    // $selectedBranchOfficeId -- tetap dropdown/forced-single seperti
    // sebelumnya.
    $lockedBranchOfficeId = old('branch_office_id', $selectedBranchOfficeId ?? null);
    $lockedBranch = $lockedBranchOfficeId ? $branchOffices->firstWhere('id', $lockedBranchOfficeId) : null;
@endphp
<div class="mb-3">
    <label class="form-label">Branch (opsional)</label>
    @if ($lockedBranch && !$errors->has('branch_office_id'))
        <input type="text" class="form-control" value="{{ $lockedBranch->name }}" disabled readonly>
        <input type="hidden" name="branch_office_id" value="{{ $lockedBranch->id }}">
        <div class="form-text">
            Mata Pelajaran / Bidang ini akan dikaitkan ke branch di atas.
            @if ($branchOffices->count() > 1)
                <a href="{{ route('jadwal.mata-pelajaran.create') }}">Ganti branch</a>
            @endif
        </div>
    @elseif ($branchOffices->count() <= 1 && $branchOffices->isNotEmpty())
        {{-- Branch-locked member: satu-satunya opsi otomatis dipakai --
             sama perlakuan seperti form-form lain di modul Jadwal/Chat. --}}
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name }}" disabled>
        <div class="form-text">Otomatis terkunci ke branch Anda.</div>
    @else
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror">
            <option value="">- Semua Branch -</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_office_id', $mataPelajaran->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
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
        <option value="active" @selected(old('status', $mataPelajaran->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $mataPelajaran->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
