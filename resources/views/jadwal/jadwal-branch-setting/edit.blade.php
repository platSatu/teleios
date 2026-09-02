@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Jam Operasional</h4>
            <p class="text-muted mb-0">{{ $branch->name }} — hari & jam buka, jam istirahat, serta default durasi/jumlah sesi generator bulanan.</p>
        </div>
        <a href="{{ route('jadwal.branch.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali ke Branch
        </a>
    </div>

    @if(session('success'))
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

    @php
        $hariOperasional = old('hari_operasional', $setting->hari_operasional ?? [1,2,3,4,5,6]);
    @endphp

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('jadwal.branch-settings.update', $branch->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label d-block">Hari Operasional</label>
                            <div class="d-flex flex-wrap gap-3">
                                @foreach(\App\Models\JadwalRutin::HARI_LABELS as $value => $label)
                                    <div class="form-check">
                                        <input type="checkbox" name="hari_operasional[]" value="{{ $value }}" id="hari_{{ $value }}"
                                            class="form-check-input" @checked(in_array($value, $hariOperasional))>
                                        <label for="hari_{{ $value }}" class="form-check-label">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                            @error('hari_operasional')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Buka</label>
                                <input type="time" name="jam_buka" class="form-control @error('jam_buka') is-invalid @enderror"
                                    value="{{ old('jam_buka', $setting ? substr($setting->jam_buka, 0, 5) : '10:00') }}" required>
                                @error('jam_buka')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Tutup</label>
                                <input type="time" name="jam_tutup" class="form-control @error('jam_tutup') is-invalid @enderror"
                                    value="{{ old('jam_tutup', $setting ? substr($setting->jam_tutup, 0, 5) : '18:00') }}" required>
                                @error('jam_tutup')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Istirahat Mulai (opsional)</label>
                                <input type="time" name="jam_istirahat_mulai" class="form-control @error('jam_istirahat_mulai') is-invalid @enderror"
                                    value="{{ old('jam_istirahat_mulai', $setting?->jam_istirahat_mulai ? substr($setting->jam_istirahat_mulai, 0, 5) : '') }}">
                                @error('jam_istirahat_mulai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Jam Istirahat Selesai (opsional)</label>
                                <input type="time" name="jam_istirahat_selesai" class="form-control @error('jam_istirahat_selesai') is-invalid @enderror"
                                    value="{{ old('jam_istirahat_selesai', $setting?->jam_istirahat_selesai ? substr($setting->jam_istirahat_selesai, 0, 5) : '') }}">
                                @error('jam_istirahat_selesai')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Durasi Sesi (menit)</label>
                                <input type="number" min="5" max="600" name="durasi_sesi_default_menit"
                                    class="form-control @error('durasi_sesi_default_menit') is-invalid @enderror"
                                    value="{{ old('durasi_sesi_default_menit', $setting->durasi_sesi_default_menit ?? 30) }}" required>
                                @error('durasi_sesi_default_menit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Default Jumlah Sesi / Bulan</label>
                                <input type="number" min="1" max="4" name="sesi_per_bulan_default"
                                    class="form-control @error('sesi_per_bulan_default') is-invalid @enderror"
                                    value="{{ old('sesi_per_bulan_default', $setting->sesi_per_bulan_default ?? 4) }}" required>
                                @error('sesi_per_bulan_default')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Maks 4 -- minggu ke-5 (kalau ada) otomatis disisakan untuk sesi pengganti.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror">
                                <option value="active" @selected(old('status', $setting->status ?? 'active') === 'active')>Active</option>
                                <option value="inactive" @selected(old('status', $setting->status ?? 'active') === 'inactive')>Inactive</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="{{ route('jadwal.branch.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
