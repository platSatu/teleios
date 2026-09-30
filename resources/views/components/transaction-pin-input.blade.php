{{-- Input PIN Transaksi untuk form yang memindahkan uang (dicek di Concerns\VerifiesTransactionPin). --}}
@if (auth()->user()->hasTransactionPin())
    <label class="form-label" for="transaction-pin">PIN Transaksi</label>
    <input type="password" name="pin" id="transaction-pin" class="form-control @error('pin') is-invalid @enderror"
        inputmode="numeric" pattern="\d{6}" minlength="6" maxlength="6" placeholder="••••••" autocomplete="off" required>
    @error('pin')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">6 digit PIN yang Anda buat di Pengaturan.</div>
@else
    <div class="alert alert-warning mb-0">
        Anda belum membuat PIN Transaksi. <a href="{{ route('user-settings.pin.edit') }}" class="alert-link">Buat PIN Sekarang</a>
    </div>
@endif
