<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konexa | Pembayaran Tagihan</title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="shortcut icon" href="{{ asset('be') }}/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand: #673ab7; --brand-dark: #4d2c91; --bg: #eef0f7; --text: #1b1b23; --muted: #6b6f80; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; color: var(--text); background: var(--bg); }
        .tp-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
        .tp-card { width: 100%; max-width: 440px; background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(103,58,183,.10); padding: 32px 28px; text-align: center; }
        .tp-spinner { width: 36px; height: 36px; border: 4px solid #e6e1f5; border-top-color: var(--brand); border-radius: 50%; margin: 0 auto 16px; animation: tp-spin 0.8s linear infinite; }
        @keyframes tp-spin { to { transform: rotate(360deg); } }
        h1 { font-size: 17px; margin: 0 0 6px; }
        p { color: var(--muted); font-size: 14px; margin: 0; }
        .tp-timer { margin-top: 18px; font-size: 13px; color: var(--muted); }
        .tp-timer strong { color: var(--brand-dark); font-size: 18px; }
        .tp-fallback { margin-top: 20px; }
        .tp-fallback a { color: var(--brand); font-size: 13px; text-decoration: none; }
        .tp-btn { display: inline-block; margin-top: 12px; padding: 10px 18px; border-radius: 10px; background: var(--brand); color: #fff; font-weight: 700; font-size: 14px; text-decoration: none; }
        #tpMessage { display: none; }
    </style>
</head>
<body>
    <div class="tp-page">
        <div class="tp-card">
            <div id="tpIdle">
                <div class="tp-spinner"></div>
                <h1>Menyiapkan Pembayaran</h1>
                <p>Jendela pembayaran Duitku akan terbuka sebentar lagi untuk<br>
                    <strong>Rp {{ number_format((float) $penerima->amount + (float) $penerima->denda_amount, 0, ',', '.') }}</strong>
                </p>
                <div class="tp-timer">Selesaikan dalam <strong id="tpTimer">--:--</strong></div>
            </div>

            <div id="tpMessage">
                <h1 id="tpMessageText"></h1>
                <a href="{{ route('tagihan.public.show', ['branchSlug' => $branchSlug, 'token' => $penerima->public_token]) }}" class="tp-btn">Kembali ke Halaman Tagihan</a>
            </div>

            @if($paymentUrl)
                <div class="tp-fallback">
                    <p style="margin-bottom:6px;">Jendela pembayaran tidak muncul?</p>
                    <a href="{{ $paymentUrl }}" target="_blank" rel="noopener">Buka Halaman Pembayaran Duitku</a>
                </div>
            @endif
        </div>
    </div>

    <script src="{{ $widgetScriptUrl }}"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var idleEl = document.getElementById('tpIdle');
        var messageEl = document.getElementById('tpMessage');
        var messageTextEl = document.getElementById('tpMessageText');
        var timerEl = document.getElementById('tpTimer');
        var returnUrl = @json(route('tagihan.public.return', ['branchSlug' => $branchSlug, 'token' => $penerima->public_token]));

        // Timer sampai expires_at -- murni informasional di halaman ini
        // (batas sebenarnya ditegakkan server-side oleh
        // App\Console\Commands\ProcessTagihanExpiry + pengecekan
        // hasExpiredWindow() di controller setiap request), sama pola
        // dengan resources/views/user/deposit/checkout.blade.php.
        var expiresAt = new Date(@json($penerima->expires_at?->toIso8601String()));
        function renderTimer() {
            if (!timerEl || isNaN(expiresAt.getTime())) return;
            var diff = Math.max(0, Math.floor((expiresAt - new Date()) / 1000));
            var m = Math.floor(diff / 60), s = diff % 60;
            timerEl.textContent = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }
        renderTimer();
        setInterval(renderTimer, 1000);

        function showMessage(text) {
            if (idleEl) idleEl.style.display = 'none';
            if (messageEl) messageEl.style.display = 'block';
            if (messageTextEl) messageTextEl.textContent = text;
        }

        if (typeof checkout === 'undefined' || !checkout || typeof checkout.process !== 'function') {
            showMessage('Jendela pembayaran gagal dimuat. Gunakan tombol di bawah untuk membuka halaman pembayaran.');
            return;
        }

        checkout.process(@json($reference), {
            defaultLanguage: 'id',
            successEvent: function () {
                showMessage('Pembayaran berhasil diterima. Terima kasih!');
                setTimeout(function () { window.location.href = returnUrl; }, 2500);
            },
            pendingEvent: function () {
                window.location.href = returnUrl;
            },
            errorEvent: function (result) {
                showMessage('Pembayaran gagal diproses Duitku' + (result && result.statusMessage ? (': ' + result.statusMessage) : '.') + ' Silakan coba lagi.');
            },
            closeEvent: function () {
                showMessage('Anda menutup jendela pembayaran sebelum selesai. Silakan coba lagi jika ingin melanjutkan.');
            }
        });
    });
    </script>
</body>
</html>
