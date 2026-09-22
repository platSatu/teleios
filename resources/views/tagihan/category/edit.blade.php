@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-lg-8">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-3">Edit Kategori Tagihan</h4>

                <form action="{{ route('tagihan.category.update', $category->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('tagihan.category._form')

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('tagihan.category.index') }}" class="btn btn-light">Kembali</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-1">Aturan Denda Bertingkat</h5>
                <p class="text-muted small mb-3">
                    Contoh: keterlambatan hari ke-1 s/d hari ke-5 dikenakan 0.5% per hari, lebih dari hari ke-5 dikenakan flat Rp 1.000. "Hari ke-" dihitung dari due date Tagihan (hari ke-0 = hari H). Aturan ini cuma berlaku untuk Tagihan yang tombol "Pakai Denda"-nya dinyalakan.
                </p>

                <div class="table-responsive mb-3">
                    <table class="table table-sm table-centered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Hari ke-</th>
                                <th>Tipe</th>
                                <th>Nilai</th>
                                <th>Frekuensi (flat)</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($category->dendaTiers as $tier)
                                <tr>
                                    <td>{{ $tier->mulai_hari_ke }} {{ $tier->sampai_hari_ke !== null ? '- '.$tier->sampai_hari_ke : 'dst.' }}</td>
                                    <td>{{ $tier->isPersenPerHari() ? 'Persen / hari' : 'Flat' }}</td>
                                    <td>{{ $tier->isPersenPerHari() ? $tier->nilai.'%' : 'Rp '.number_format($tier->nilai, 0, ',', '.') }}</td>
                                    <td>{{ $tier->isFlat() ? ($tier->frekuensi_flat === 'sekali' ? 'Sekali' : 'Per hari') : '-' }}</td>
                                    <td class="text-end">
                                        <form action="{{ route('tagihan.denda-tier.destroy', [$category->id, $tier->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus aturan denda ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Belum ada aturan denda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <form action="{{ route('tagihan.denda-tier.store', $category->id) }}" method="POST" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label small">Mulai hari ke-</label>
                        <input type="number" name="mulai_hari_ke" class="form-control form-control-sm" min="0" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Sampai hari ke- (kosong = dst.)</label>
                        <input type="number" name="sampai_hari_ke" class="form-control form-control-sm" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Tipe</label>
                        <select name="tipe" id="tierTipe" class="form-select form-select-sm" required>
                            <option value="persen_per_hari">Persen / hari</option>
                            <option value="flat">Flat</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Nilai</label>
                        <input type="number" step="0.0001" min="0" name="nilai" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-2" id="frekuensiFlatWrap" style="display:none;">
                        <label class="form-label small">Frekuensi</label>
                        <select name="frekuensi_flat" class="form-select form-select-sm">
                            <option value="sekali">Sekali</option>
                            <option value="per_hari">Per hari</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Tambah</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tipeEl = document.getElementById('tierTipe');
    var wrapEl = document.getElementById('frekuensiFlatWrap');
    if (!tipeEl || !wrapEl) return;
    function toggle() {
        wrapEl.style.display = tipeEl.value === 'flat' ? '' : 'none';
    }
    tipeEl.addEventListener('change', toggle);
    toggle();
});
</script>
@endsection
