@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-1">Buat Tagihan Baru</h4>
                <p class="text-muted small mb-3">
                    Semua pelanggan yang berlangganan kategori yang dipilih akan otomatis ditagih sejumlah nominal di bawah (kecuali pelanggan yang punya nominal override sendiri).
                </p>

                @if($categories->isEmpty())
                    <div class="alert alert-warning">Belum ada Kategori Tagihan aktif. Buat kategori dulu sebelum membuat Tagihan.</div>
                @else
                    <form action="{{ route('tagihan.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <select name="tagihan_category_id" id="categorySelect" class="form-select @error('tagihan_category_id') is-invalid @enderror" required>
                                <option value="">- Pilih Kategori -</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                        data-default-amount="{{ $cat->default_amount }}"
                                        data-denda-enabled="{{ $cat->denda_enabled ? 1 : 0 }}"
                                        @selected(old('tagihan_category_id') == $cat->id)>
                                        {{ $cat->name }} ({{ $cat->branchOffice->name ?? '-' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('tagihan_category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Tagihan</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" placeholder="Misal: Uang Sekolah Januari 2026" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nominal</label>
                            <input type="number" step="0.01" min="0" name="amount" id="amountInput" class="form-control @error('amount') is-invalid @enderror"
                                value="{{ old('amount') }}" required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Jatuh Tempo</label>
                            <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                                value="{{ old('due_date') }}" required>
                            @error('due_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" name="pakai_denda" id="pakaiDenda" class="form-check-input" value="1" @checked(old('pakai_denda'))>
                            <label class="form-check-label" for="pakaiDenda">Pakai denda keterlambatan untuk Tagihan ini</label>
                            <div class="form-text">Terisi otomatis mengikuti pengaturan kategori, boleh diubah manual.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Buat Tagihan</button>
                            <a href="{{ route('tagihan.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var categorySelect = document.getElementById('categorySelect');
    var amountInput = document.getElementById('amountInput');
    var pakaiDenda = document.getElementById('pakaiDenda');
    if (!categorySelect) return;

    categorySelect.addEventListener('change', function () {
        var opt = categorySelect.options[categorySelect.selectedIndex];
        if (!opt) return;
        var defaultAmount = opt.getAttribute('data-default-amount');
        if (defaultAmount && amountInput && !amountInput.value) {
            amountInput.value = defaultAmount;
        }
        if (pakaiDenda) {
            pakaiDenda.checked = opt.getAttribute('data-denda-enabled') === '1';
        }
    });
});
</script>
@endsection
