@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-1">
                        <h4 class="mb-0">Pengaturan Duitku Disbursement</h4>
                        <form action="{{ route('duitku-disbursement-setting.test') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="ri-plug-line"></i> Tes Koneksi</button>
                        </form>
                    </div>
                    <p class="text-muted mb-4">
                        Kredensial untuk <strong>Tarik Saldo</strong> (kirim dana ke rekening user). Berbeda dengan Merchant Code &amp; API Key
                        pembayaran di <a href="{{ route('duitku-setting.edit') }}">Pengaturan Duitku</a>. User ID, Email, dan Secret Key
                        Disbursement dikirim Duitku ke email akun Anda setelah fitur Disbursement diaktifkan.
                    </p>

                    @if (session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">{{ $errors->first() }}</div>
                    @endif

                    <form action="{{ route('duitku-disbursement-setting.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label class="form-label" for="mode">Mode Aktif</label>
                            <select name="mode" id="mode" class="form-select">
                                <option value="sandbox" @selected(old('mode', $setting->mode) === 'sandbox')>Sandbox (uji coba, uang tidak sungguhan)</option>
                                <option value="production" @selected(old('mode', $setting->mode) === 'production')>Production (uang sungguhan)</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="withdrawal_fee">Biaya Tarik Saldo per Transaksi (Rp)</label>
                            <input type="number" name="withdrawal_fee" id="withdrawal_fee" class="form-control @error('withdrawal_fee') is-invalid @enderror"
                                min="0" max="1000000" step="1" required value="{{ old('withdrawal_fee', $setting->withdrawalFee()) }}">
                            <div class="form-text">Dipotong dari jumlah yang ditarik user. Contoh: biaya Rp 2.500, user tarik Rp 50.000 → saldo terpotong Rp 50.000, diterima di rekening Rp 47.500. Berlaku untuk pengajuan baru; pengajuan yang sudah ada memakai biaya saat diajukan.</div>
                            @error('withdrawal_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row">
                            @foreach (['sandbox' => 'Sandbox', 'production' => 'Production'] as $mode => $label)
                                <div class="col-md-6 mb-3">
                                    <div class="card border mb-0">
                                        <div class="card-body">
                                            <h6 class="mb-3">{{ $label }}</h6>

                                            <div class="mb-3">
                                                <label class="form-label" for="{{ $mode }}_user_id">User ID</label>
                                                <input type="text" name="{{ $mode }}_user_id" id="{{ $mode }}_user_id" class="form-control" inputmode="numeric"
                                                    value="{{ old($mode.'_user_id', $setting->{$mode.'_user_id'}) }}" placeholder="Angka, mis. 3551">
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label" for="{{ $mode }}_email">Email</label>
                                                <input type="email" name="{{ $mode }}_email" id="{{ $mode }}_email" class="form-control"
                                                    value="{{ old($mode.'_email', $setting->{$mode.'_email'}) }}" placeholder="Email akun Duitku">
                                            </div>

                                            <div class="mb-0">
                                                <label class="form-label" for="{{ $mode }}_secret_key">Secret Key</label>
                                                <input type="password" name="{{ $mode }}_secret_key" id="{{ $mode }}_secret_key" class="form-control" autocomplete="new-password"
                                                    placeholder="{{ $setting->{$mode.'_secret_key'} ? 'Sudah tersimpan — isi untuk mengganti' : 'Belum diisi' }}">
                                                <div class="form-text">Disimpan terenkripsi. Kosongkan kalau tidak ingin mengganti.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <x-transaction-pin-input />
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
