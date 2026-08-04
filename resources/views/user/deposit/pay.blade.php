@extends('layouts.dashboard')
@section('content')
<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-6 col-md-8">

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center">

                    <div id="payStatusIdle">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Memuat...</span>
                        </div>
                        <h5 class="mb-1">Menyiapkan Pembayaran</h5>
                        <p class="text-muted small mb-0">
                            Jendela pembayaran Duitku akan terbuka sebentar lagi untuk
                            <strong>Rp {{ number_format($deposit->amount, 0, ',', '.') }}</strong>.
                        </p>
                    </div>

                    <div id="payStatusMessage" class="d-none">
                        <i class="ri-information-line fs-1 text-muted mb-2 d-block"></i>
                        <p class="mb-3" id="payStatusText"></p>
                        <a href="{{ route('deposit.checkout', $deposit) }}" class="btn btn-primary">Coba Lagi</a>
                    </div>

                    @if ($paymentUrl)
                        <hr class="my-4">
                        <p class="text-muted small mb-2">Jendela pembayaran tidak muncul?</p>
                        <a href="{{ $paymentUrl }}" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm">
                            Buka Halaman Pembayaran Duitku
                        </a>
                    @endif

                </div>
            </div>

        </div>

    </div>

</div>

<script src="{{ $widgetScriptUrl }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var idleEl = document.getElementById('payStatusIdle');
    var messageEl = document.getElementById('payStatusMessage');
    var messageTextEl = document.getElementById('payStatusText');
    var returnUrl = @json(route('deposit.duitku.return', $deposit));

    function showMessage(text) {
        if (idleEl) idleEl.classList.add('d-none');
        if (messageEl) messageEl.classList.remove('d-none');
        if (messageTextEl) messageTextEl.textContent = text;
    }

    if (typeof checkout === 'undefined' || !checkout || typeof checkout.process !== 'function') {
        // Widget script failed to load (blocked, offline, etc.) — the
        // plain paymentUrl link above is the fallback, no need to
        // retry automatically.
        showMessage('Jendela pembayaran gagal dimuat. Gunakan tombol di bawah untuk membuka halaman pembayaran.');
        return;
    }

    checkout.process(@json($reference), {
        defaultLanguage: 'id',
        successEvent: function () {
            // Informational redirect only — the wallet is credited by
            // the server-to-server webhook (DuitkuCallbackController),
            // never by this client-side event.
            window.location.href = returnUrl;
        },
        pendingEvent: function () {
            window.location.href = returnUrl;
        },
        errorEvent: function (result) {
            showMessage('Pembayaran gagal diproses Duitku' + (result && result.statusMessage ? (': ' + result.statusMessage) : '.') + ' Silakan coba lagi.');
        },
        closeEvent: function () {
            // User closed the popup without finishing — deposit stays
            // PENDING (see cancelCheckout()'s own timeout for the
            // "gave up entirely" case), let them retry from checkout.
            showMessage('Anda menutup jendela pembayaran sebelum selesai. Silakan coba lagi jika ingin melanjutkan.');
        }
    });
});
</script>
@endsection
