@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-1">Pengaturan Point / Cashback Pembelian</h4>
                    <p class="text-muted mb-4">Atur berapa rupiah point yang didapat user setiap pembelian package berhasil.</p>

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
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

                    <form action="{{ route('point-setting.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="point_enabled" name="point_enabled" value="1" {{ $enabled ? 'checked' : '' }}>
                            <label class="form-check-label" for="point_enabled">Aktifkan point/cashback pembelian</label>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="point_amount_threshold" class="form-label">Setiap Kelipatan Pembelian (Rp) <span class="text-danger">*</span></label>
                                <input type="number" step="1" min="1" name="point_amount_threshold" id="point_amount_threshold" class="form-control"
                                    value="{{ old('point_amount_threshold', $threshold) }}" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="point_value" class="form-label">Dapat Point (Rp) <span class="text-danger">*</span></label>
                                <input type="number" step="1" min="0" name="point_value" id="point_value" class="form-control"
                                    value="{{ old('point_value', $pointValue) }}" required>
                            </div>
                        </div>

                        <div class="alert alert-info fs-14 mb-4">
                            <i class="ri-information-line"></i>
                            Contoh dengan nilai saat ini: pembelian Rp {{ number_format($threshold * 2, 0, ',', '.') }}
                            akan mendapat point Rp {{ number_format($pointValue * 2, 0, ',', '.') }}
                            (dihitung per kelipatan penuh Rp {{ number_format($threshold, 0, ',', '.') }}, sisa tidak dibulatkan ke atas).
                            Point langsung masuk ke saldo wallet pembeli.
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
