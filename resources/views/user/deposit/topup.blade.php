@extends('layouts.dashboard')
@section('content')
<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-6 col-md-8">

            @if(session('success'))
                <div class="alert alert-success d-flex align-items-start gap-2 border-0 shadow-sm" role="alert">
                    <i class="ri-checkbox-circle-fill fs-4"></i>
                    <div>
                        <strong class="d-block">Berhasil!</strong>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger d-flex align-items-start gap-2 border-0 shadow-sm" role="alert">
                    <i class="ri-error-warning-fill fs-4"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4">

                    <div class="d-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h5 class="mb-1">Top Up Saldo</h5>
                            <p class="text-muted small mb-0">Isi saldo wallet Anda dengan cepat &amp; aman</p>
                        </div>
                        <a href="{{ route('deposit.history') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ri-history-line"></i> Riwayat
                        </a>
                    </div>

                    {{-- Uses the theme's own --pe-primary / --pe-primary-rgb CSS
                         variables (same ones driving .btn-primary, the sidebar
                         logo, etc.) instead of a hardcoded hex, so this card
                         always matches the active theme color automatically. --}}
                    <div class="rounded-4 p-4 mb-4 text-white" style="background: linear-gradient(135deg, var(--pe-primary), rgba(var(--pe-primary-rgb), .75));">
                        <p class="mb-1 small" style="opacity: .85;">Saldo Anda Saat Ini</p>
                        <h3 class="mb-0 fw-bold">Rp {{ number_format($walletBalance, 0, ',', '.') }}</h3>
                    </div>

                    <form action="{{ route('deposit.store') }}" method="POST">

                        @csrf

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="amount_display">
                                Nominal Deposit
                            </label>

                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light">Rp</span>

                                {{-- Visible, formatted (Indonesian thousands
                                     separator, e.g. 10.000) input. Not
                                     submitted directly — its only job is
                                     display + editing. --}}
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    class="form-control @error('amount') is-invalid @enderror"
                                    id="amount_display"
                                    placeholder="0"
                                    value="{{ old('amount') }}"
                                    required>

                                @error('amount')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Actual submitted value: plain digits only. --}}
                            <input type="hidden" name="amount" id="amount" value="{{ old('amount') }}">

                            <small class="text-muted">
                                Minimal deposit Rp10.000
                            </small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold small text-muted text-uppercase">
                                Pilih Cepat
                            </label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ([50000, 100000, 200000, 500000, 1000000, 2000000] as $preset)
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm quick-amount-btn"
                                        data-amount="{{ $preset }}">
                                        Rp {{ number_format($preset, 0, ',', '.') }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- No payment-method picker here — Duitku hosts its own
                             (VA/e-wallet/QRIS/dll) on the next screen after the
                             confirmation step below, see deposit.checkout. --}}
                        <div class="alert alert-light border small mb-4 d-flex align-items-start gap-2">
                            <i class="ri-shield-check-line fs-5"></i>
                            <div>Pembayaran diproses aman melalui <strong>Duitku</strong>. Anda akan diminta konfirmasi sebelum lanjut ke halaman pembayaran.</div>
                        </div>

                        <button
                            class="btn btn-primary btn-lg w-100 d-flex align-items-center justify-content-center gap-2">
                            <i class="ri-wallet-3-line"></i>
                            Lanjutkan Top Up
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

{{-- No @stack('scripts') in layouts.dashboard, so this goes inline
     inside the content section instead (same pattern already used by
     resources/views/chat/inbox/inbox.blade.php). --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    var display = document.getElementById('amount_display');
    var hidden = document.getElementById('amount');

    if (!display || !hidden) {
        return;
    }

    function formatWithDots(digits) {
        return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    // How many actual digits sit before the cursor right now — used
    // instead of a raw character index so reformatting (which only
    // adds/removes "." separators) never shifts where the cursor lands
    // relative to the digits the user actually typed. Without this,
    // every keystroke silently snaps the cursor to the end of the
    // field, which is what makes backspace feel like it needs to be
    // pressed more than once to remove the character you're looking at.
    function countDigitsBeforeCursor(value, cursorPos) {
        var count = 0;
        for (var i = 0; i < cursorPos && i < value.length; i++) {
            if (value[i] >= '0' && value[i] <= '9') {
                count++;
            }
        }
        return count;
    }

    function cursorPosForDigitCount(value, digitCount) {
        if (digitCount <= 0) {
            return 0;
        }
        var count = 0;
        for (var i = 0; i < value.length; i++) {
            if (value[i] >= '0' && value[i] <= '9') {
                count++;
                if (count === digitCount) {
                    return i + 1;
                }
            }
        }
        return value.length;
    }

    function reformat(cursorPos) {
        var rawValue = display.value;
        var digitsBeforeCursor = countDigitsBeforeCursor(rawValue, cursorPos);

        var digitsOnly = rawValue.replace(/\D/g, '');
        var formatted = formatWithDots(digitsOnly);

        display.value = formatted;
        hidden.value = digitsOnly;

        var newCursorPos = cursorPosForDigitCount(formatted, digitsBeforeCursor);
        display.setSelectionRange(newCursorPos, newCursorPos);
    }

    display.addEventListener('input', function () {
        reformat(display.selectionStart);
    });

    // Repopulate correctly on validation-error reload (old('amount') is
    // raw digits).
    if (display.value) {
        var digitsOnly = display.value.replace(/\D/g, '');
        display.value = formatWithDots(digitsOnly);
        hidden.value = digitsOnly;
    }

    // Quick-amount chips fill the field directly (same formatting path
    // as typing), and re-focus so the user can still fine-tune it.
    document.querySelectorAll('.quick-amount-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var amount = btn.getAttribute('data-amount');
            display.value = formatWithDots(amount);
            hidden.value = amount;
            display.focus();
        });
    });
});
</script>
@endsection
