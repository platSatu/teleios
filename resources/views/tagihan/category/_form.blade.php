<div class="mb-3">
    <label class="form-label">Nama Kategori</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $category->name ?? '') }}" placeholder="Misal: Uang Sekolah, Uang Pangkal, Uang Seragam" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

@php
    $lockedBranchOfficeId = old('branch_office_id', $selectedBranchOfficeId ?? ($category->branch_office_id ?? null));
    $lockedBranch = $lockedBranchOfficeId ? $branchOffices->firstWhere('id', $lockedBranchOfficeId) : null;
@endphp
<div class="mb-3">
    <label class="form-label">Branch</label>
    @if ($lockedBranch && !$errors->has('branch_office_id') && ($category ?? null))
        <input type="text" class="form-control" value="{{ $lockedBranch->name }}" disabled readonly>
        <input type="hidden" name="branch_office_id" value="{{ $lockedBranch->id }}">
    @elseif ($branchOffices->count() <= 1 && $branchOffices->isNotEmpty())
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name }}" disabled>
    @else
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror" required>
            <option value="">- Pilih Branch -</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected($lockedBranchOfficeId == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        @error('branch_office_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    @endif
</div>

{{-- Nominal Default/Berulang tiap bulan/Aktifkan denda dipindah keluar dari
     form ini (23 September 2026 redesign): nominal selalu diisi manual per
     Tagihan (tidak pernah benar-benar dipakai sebagai default), "berulang"
     cuma label tanpa efek nyata, dan denda sekarang wajib dikonfigurasi
     lewat halaman "Setting Tagihan" (3 mode: Flat/Persentase/Persentase+
     Flat -- lihat TagihanCategorySettingController) begitu kategori ini
     tersimpan, bukan sekadar on/off di sini. --}}
<div class="mb-3">
    <label class="form-label">Deskripsi (opsional)</label>
    <textarea name="deskripsi" rows="3" class="form-control @error('deskripsi') is-invalid @enderror"
        placeholder="Catatan singkat soal kategori ini, ditampilkan buat admin lain">{{ old('deskripsi', $category->deskripsi ?? '') }}</textarea>
    @error('deskripsi')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $category->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $category->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
