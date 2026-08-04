@extends('layouts.dashboard')
@section('content')
<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-6 col-md-8">

            @if(session('error'))
                <div class="alert alert-danger d-flex align-items-start gap-2 border-0 shadow-sm" role="alert">
                    <i class="ri-error-warning-fill fs-4"></i>
                    <div>{{ session('error') }}</div>
                </div>
            @endif

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <div class="d-flex align-items-start justify-content-between mb-4 gap-3">
                        <div>
                            <h5 class="mb-1">Konfirmasi Pembayaran</h5>
                            <p class="text-muted small mb-0">Periksa kembali sebelum lanjut ke Duitku.</p>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <div class="text-muted small mb-1">Selesaikan dalam</div>
                            <div class="fw-bold fs-5 text-danger" id="checkoutTimer">--:--</div>
                        </div>
                    </div>

                    <table class="table table-sm table-borderless mb-4">
                        <tr>
                            <td class="text-muted" style="width: 40%">Referensi</td>
                            <td class="fw-semibold">{{ $deposit->reference_number }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Nominal</td>
                            <td class="fw-bold fs-5">Rp {{ number_format($deposit->amount, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td><span class="badge bg-warning-subtle text-warning">Menunggu Pembayaran</span></td>
                        </tr>
                    </table>

                    <div class="alert alert-info d-flex align-items-start gap-2 border-0" role="alert">
                        <i class="ri-information-line fs-4"></i>
                        <div class="small">
                            Anda akan diarahkan ke halaman Duitku untuk memilih metode pembayaran
                            (Virtual Account, e-wallet, QRIS, dll) dan menyelesaikan pembayaran.
                            Saldo wallet bertambah otomatis begitu pembayaran dikonfirmasi.
                            Jika waktu di atas habis, deposit ini otomatis dibatalkan.
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        {{-- Same form the countdown timer below auto-submits on
                             timeout — "Batalkan" and "waktu habis" both end up
                             marking this deposit FAILED via cancelCheckout(),
                             instead of leaving it stuck PENDING forever. --}}
                        <form action="{{ route('deposit.checkout.cancel', $deposit) }}" method="POST" class="w-50" id="cancelCheckoutForm">
                            @csrf
                            <button type="submit" class="btn btn-light w-100">Batalkan</button>
                        </form>
                        <form action="{{ route('deposit.checkout.duitku', $deposit) }}" method="POST" class="w-50">
                            @csrf
                            <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center gap-2">
                                Lanjutkan ke Duitku <i class="ri-arrow-right-line"></i>
                            </button>
                        </form>
                    </div>

                </div>
            </div>

        </div>

    </div>

</div>

{{-- No @stack('scripts') in layouts.dashboard, so this goes inline
     inside the content section (same pattern already used by
     resources/views/user/deposit/topup.blade.php). --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    var totalSeconds = {{ (int) $checkoutTimeoutMinutes * 60 }};
    var timerEl = document.getElementById('checkoutTimer');
    var cancelForm = document.getElementById('cancelCheckoutForm');

    if (!timerEl || !cancelForm) {
        return;
    }

    function render() {
        var minutes = Math.floor(totalSeconds / 60);
        var seconds = totalSeconds % 60;
        timerEl.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }

    render();

    var interval = setInterval(function () {
        totalSeconds--;

        if (totalSeconds <= 0) {
            clearInterval(interval);
            timerEl.textContent = '00:00';
            // Waktu konfirmasi habis — submit form batal yang sama
            // dipakai tombol "Batalkan", supaya deposit ditandai FAILED
            // (lewat DepositController::cancelCheckout()) alih-alih
            // menggantung PENDING selamanya.
            cancelForm.submit();
            return;
        }

        render();
    }, 1000);
});
</script>
@endsection
