@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-1">Pengaturan Duitku</h4>
                    <p class="text-muted mb-4">
                        Kredensial merchant Duitku untuk flow top-up saldo wallet. Sandbox dan Production disimpan
                        terpisah — isi keduanya sekali, lalu tinggal pilih mode mana yang aktif kapan pun tanpa perlu
                        mengetik ulang.
                    </p>

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

                    <form action="{{ route('duitku-setting.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label class="form-label">Mode Aktif</label>
                            <select name="mode" class="form-select">
                                <option value="sandbox" @selected(old('mode', $setting->mode) === 'sandbox')>Sandbox</option>
                                <option value="production" @selected(old('mode', $setting->mode) === 'production')>Production</option>
                            </select>
                            <div class="form-text">
                                Ini yang menentukan kredensial mana (Sandbox atau Production di bawah) yang benar-benar
                                dipakai App\Services\Payment\DuitkuService saat membuat invoice/top-up baru.
                            </div>
                        </div>

                        {{-- Biaya QRIS (5 Oktober 2026): Duitku tidak mengizinkan biaya QRIS dibebankan ke customer
                             dari dashboard-nya, jadi kalau saklar ini aktif, biaya ditambahkan oleh sistem saat top up. --}}
                        <div class="card border mb-4">
                            <div class="card-body">
                                <h6 class="mb-1">Biaya QRIS</h6>
                                <p class="text-muted small mb-3">Kalau aktif, customer yang top up lewat QRIS membayar nominal + biaya QRIS. Saldo yang masuk tetap sesuai nominal top up.</p>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="qris_fee_to_customer" name="qris_fee_to_customer" value="1"
                                        @checked(old('qris_fee_to_customer', $setting->qris_fee_to_customer))>
                                    <label class="form-check-label" for="qris_fee_to_customer">Biaya QRIS dibebankan ke customer</label>
                                </div>
                                <div class="row g-3">
                                    <div class="col-sm-6">
                                        <label class="form-label">Tarif QRIS (%)</label>
                                        <input type="number" name="qris_fee_percent" class="form-control @error('qris_fee_percent') is-invalid @enderror"
                                            min="0" max="10" step="0.01" required value="{{ old('qris_fee_percent', $setting->qris_fee_percent ?? 0.7) }}">
                                        @error('qris_fee_percent')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label">Kode metode QRIS di Duitku</label>
                                        <input type="text" name="qris_payment_code" class="form-control text-uppercase @error('qris_payment_code') is-invalid @enderror"
                                            maxlength="10" required value="{{ old('qris_payment_code', $setting->qris_payment_code ?? 'SQ') }}">
                                        <div class="form-text">Lihat kolom Metode di Data Deposit untuk pembayaran QRIS sebelumnya (mis. SQ).</div>
                                        @error('qris_payment_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="form-text text-warning mt-2">Catatan: Bank Indonesia mengimbau biaya QRIS tidak dibebankan ke pembeli. Aktifkan sesuai kebijakan bisnis Anda.</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card border">
                                    <div class="card-body">
                                        <h6 class="mb-3">Sandbox</h6>

                                        <div class="mb-3">
                                            <label class="form-label">Merchant Code</label>
                                            <input type="text" name="sandbox_merchant_code" class="form-control"
                                                value="{{ old('sandbox_merchant_code', $setting->sandbox_merchant_code) }}"
                                                placeholder="mis. DS12345">
                                        </div>

                                        <div class="mb-0">
                                            <label class="form-label">API Key</label>
                                            <input type="password" name="sandbox_api_key" class="form-control" autocomplete="new-password"
                                                placeholder="{{ $setting->sandbox_api_key ? 'Sudah tersimpan — isi untuk mengganti' : 'Belum diisi' }}">
                                            <div class="form-text">Disimpan terenkripsi. Kosongkan kalau tidak ingin mengganti key yang sudah ada.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="card border">
                                    <div class="card-body">
                                        <h6 class="mb-3">Production</h6>

                                        <div class="mb-3">
                                            <label class="form-label">Merchant Code</label>
                                            <input type="text" name="production_merchant_code" class="form-control"
                                                value="{{ old('production_merchant_code', $setting->production_merchant_code) }}"
                                                placeholder="mis. DS12345">
                                        </div>

                                        <div class="mb-0">
                                            <label class="form-label">API Key</label>
                                            <input type="password" name="production_api_key" class="form-control" autocomplete="new-password"
                                                placeholder="{{ $setting->production_api_key ? 'Sudah tersimpan — isi untuk mengganti' : 'Belum diisi' }}">
                                            <div class="form-text">Disimpan terenkripsi. Kosongkan kalau tidak ingin mengganti key yang sudah ada.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
