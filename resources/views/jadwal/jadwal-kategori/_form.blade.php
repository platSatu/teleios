<input type="hidden" name="jadwal_mata_pelajaran_id" value="{{ $mataPelajaran->id }}">

<div class="mb-3">
    <label class="form-label">Nama Kategori</label>
    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
        value="{{ old('name', $kategori->name ?? '') }}" placeholder="Misal: Classic Level 1, Pop" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Harga per Sesi (Rp)</label>
    <input type="number" step="0.01" min="0" name="harga_per_sesi" class="form-control @error('harga_per_sesi') is-invalid @enderror"
        value="{{ old('harga_per_sesi', $kategori->harga_per_sesi ?? '') }}" placeholder="400000" required>
    @error('harga_per_sesi')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-6 mb-3">
        <label class="form-label">Persentase Company (%)</label>
        <input type="number" step="0.01" min="0" max="100" name="persentase_company" id="persentase_company"
            class="form-control @error('persentase_company') is-invalid @enderror"
            value="{{ old('persentase_company', $kategori->persentase_company ?? '') }}" placeholder="40" required>
        @error('persentase_company')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-6 mb-3">
        <label class="form-label">Persentase Pengajar (%)</label>
        <input type="number" step="0.01" min="0" max="100" name="persentase_pengajar" id="persentase_pengajar"
            class="form-control @error('persentase_pengajar') is-invalid @enderror"
            value="{{ old('persentase_pengajar', $kategori->persentase_pengajar ?? '') }}" placeholder="60" required>
        @error('persentase_pengajar')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
<div class="form-text mb-3">Persentase Company + Pengajar harus berjumlah 100.</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select @error('status') is-invalid @enderror">
        <option value="active" @selected(old('status', $kategori->status ?? 'active') === 'active')>Active</option>
        <option value="inactive" @selected(old('status', $kategori->status ?? 'active') === 'inactive')>Inactive</option>
    </select>
    @error('status')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<script>
// Kenyamanan saja (bukan validasi utama -- itu tetap di server): isi
// otomatis sisi lain saat salah satu persentase diketik, supaya admin
// tidak perlu menghitung manual supaya jumlahnya 100.
(function () {
    var company = document.getElementById('persentase_company');
    var pengajar = document.getElementById('persentase_pengajar');
    if (!company || !pengajar) return;

    company.addEventListener('input', function () {
        if (company.value !== '') {
            var v = parseFloat(company.value);
            if (!isNaN(v) && v >= 0 && v <= 100) pengajar.value = (100 - v).toString();
        }
    });
    pengajar.addEventListener('input', function () {
        if (pengajar.value !== '') {
            var v = parseFloat(pengajar.value);
            if (!isNaN(v) && v >= 0 && v <= 100) company.value = (100 - v).toString();
        }
    });
})();
</script>
