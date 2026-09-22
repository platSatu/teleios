@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <h4 class="mb-1">Edit Tagihan</h4>
                <p class="text-muted small mb-3">
                    Nominal &amp; pengaturan denda terkunci sejak Tagihan dibuat (supaya invoice yang sudah tersalin ke pelanggan tidak berubah diam-diam). Kalau nominalnya salah, batalkan Tagihan ini dan buat yang baru.
                </p>

                <form action="{{ route('tagihan.update', $tagihan->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Nama Tagihan</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $tagihan->name) }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Jatuh Tempo</label>
                        <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                            value="{{ old('due_date', $tagihan->due_date->format('Y-m-d')) }}" required>
                        @error('due_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" @selected(old('status', $tagihan->status) === 'active')>Active</option>
                            <option value="dibatalkan" @selected(old('status', $tagihan->status) === 'dibatalkan')>Dibatalkan</option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('tagihan.show', $tagihan->id) }}" class="btn btn-light">Kembali</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
