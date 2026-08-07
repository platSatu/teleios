@csrf
@if($item)
    @method('PUT')
@endif

<div class="mb-3">
    <label class="form-label">Cabang <span class="text-danger">*</span></label>
    @if ($branchOffices->count() > 1)
        <select name="branch_office_id" class="form-select @error('branch_office_id') is-invalid @enderror" required>
            <option value="">-- Pilih Cabang --</option>
            @foreach ($branchOffices as $branch)
                <option value="{{ $branch->id }}" @selected(old('branch_office_id', $item->branch_office_id ?? '') == $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
    @else
        <input type="hidden" name="branch_office_id" value="{{ $branchOffices->first()->id ?? '' }}">
        <input type="text" class="form-control" value="{{ $branchOffices->first()->name ?? '-' }}" disabled>
    @endif
    @error('branch_office_id')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Nama Mata Pelajaran <span class="text-danger">*</span></label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $item->name ?? '') }}" required maxlength="255">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Deskripsi</label>
    <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3">{{ old('description', $item->description ?? '') }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Durasi Standar per Pertemuan (menit)</label>
    <input type="number" name="durasi_menit" class="form-control @error('durasi_menit') is-invalid @enderror" value="{{ old('durasi_menit', $item->durasi_menit ?? '') }}" min="5" max="600" placeholder="Misal: 60">
    @error('durasi_menit')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    <div class="form-text">Opsional — dipakai untuk otomatis mengisi "Jam Selesai" saat membuat Jadwal Kelas untuk mata pelajaran ini. Kalau kadang jadi lebih lama/lebih singkat di hari tertentu, tetap bisa diubah manual per tanggal lewat "Ubah Jam" di halaman detail kelas.</div>
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $item->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $item->status ?? '') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="{{ route('jadwal.mata-pelajaran.index') }}" class="btn btn-light">Batal</a>
</div>
