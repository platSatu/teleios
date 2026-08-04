@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-1">{{ $hasPin ? 'Ubah PIN Transaksi' : 'Buat PIN Transaksi' }}</h4>
                    <p class="text-muted mb-4">PIN 6 digit ini dipakai untuk konfirmasi setiap transfer saldo ke user lain.</p>

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

                    <form action="{{ route('user-settings.pin.update') }}" method="POST">
                        @csrf
                        @method('PUT')

                        @if ($hasPin)
                            <div class="mb-3">
                                <label for="current_pin" class="form-label">PIN Saat Ini <span class="text-danger">*</span></label>
                                <input type="password" inputmode="numeric" maxlength="6" name="current_pin" id="current_pin" class="form-control" required autocomplete="off">
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="pin" class="form-label">PIN Baru (6 digit) <span class="text-danger">*</span></label>
                            <input type="password" inputmode="numeric" maxlength="6" name="pin" id="pin" class="form-control" required autocomplete="off">
                        </div>

                        <div class="mb-4">
                            <label for="pin_confirmation" class="form-label">Ulangi PIN Baru <span class="text-danger">*</span></label>
                            <input type="password" inputmode="numeric" maxlength="6" name="pin_confirmation" id="pin_confirmation" class="form-control" required autocomplete="off">
                        </div>

                        <button type="submit" class="btn btn-primary">{{ $hasPin ? 'Simpan PIN Baru' : 'Buat PIN' }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
