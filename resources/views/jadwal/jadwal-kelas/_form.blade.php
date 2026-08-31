<div class="mb-3">
    <label class="form-label">Mata Pelajaran / Bidang (opsional)</label>
    <select name="jadwal_mata_pelajaran_id" class="form-select @error('jadwal_mata_pelajaran_id') is-invalid @enderror">
        <option value="">- Tidak ditentukan -</option>
        @foreach ($mataPelajarans as $mp)
            <option value="{{ $mp->id }}" @selected(old('jadwal_mata_pelajaran_id', $selectedMataPelajaranId ?? '') == $mp->id)>{{ $mp->name }}</option>
        @endforeach
    </select>
    @error('jadwal_mata_pelajaran_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Pengajar</label>
        <select name="pengajar_id" class="form-select @error('pengajar_id') is-invalid @enderror" required>
            <option value="">- Pilih Pengajar -</option>
            @foreach ($teamMembers as $member)
                <option value="{{ $member->id }}" @selected(old('pengajar_id', $kelas->pengajar_id ?? '') == $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
        @error('pengajar_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Murid</label>
        <select name="student_id" class="form-select @error('student_id') is-invalid @enderror" required>
            <option value="">- Pilih Murid -</option>
            @foreach ($teamMembers as $member)
                <option value="{{ $member->id }}" @selected(old('student_id', $kelas->student_id ?? '') == $member->id)>{{ $member->name }}</option>
            @endforeach
        </select>
        @error('student_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label">Waktu Mulai (opsional)</label>
        <input type="datetime-local" name="start_time"
            value="{{ old('start_time', isset($kelas->start_time) && $kelas->start_time ? $kelas->start_time->format('Y-m-d\TH:i') : '') }}"
            class="form-control @error('start_time') is-invalid @enderror">
        @error('start_time')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label class="form-label">Waktu Selesai (opsional)</label>
        <input type="datetime-local" name="end_time"
            value="{{ old('end_time', isset($kelas->end_time) && $kelas->end_time ? $kelas->end_time->format('Y-m-d\TH:i') : '') }}"
            class="form-control @error('end_time') is-invalid @enderror">
        @error('end_time')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Deskripsi (opsional)</label>
    <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror"
        placeholder="Catatan tambahan untuk kelas ini...">{{ old('description', $kelas->description ?? '') }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $kelas->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $kelas->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
