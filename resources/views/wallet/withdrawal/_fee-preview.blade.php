{{--
    Input jumlah + rincian biaya tarik saldo (2 Oktober 2026) -- dipakai form
    Tarik Saldo pribadi & branch. Taruh di dalam <form>, setelah label.
    Variabel: $withdrawalFee (int Rupiah).

    Jumlah diketik dengan titik ribuan (10.000) seperti Top Up Saldo; yang
    dikirim ke server hanya angka (input hidden "amount"). Semua hanya
    tampilan: angka sebenarnya divalidasi & dihitung ulang di server
    (WalletWithdrawalService::request()).
--}}
@php
    $minNet = \App\Services\Wallet\WalletWithdrawalService::MIN_TRANSFER;
    $minAmount = $withdrawalFee + $minNet;
    $oldAmount = preg_replace('/\D/', '', (string) old('amount'));
@endphp
<div class="input-group">
    <span class="input-group-text bg-light">Rp</span>
    <input type="text" inputmode="numeric" autocomplete="off" placeholder="0" required
        class="form-control js-amount-display @error('amount') is-invalid @enderror"
        value="{{ $oldAmount !== '' ? number_format((int) $oldAmount, 0, ',', '.') : '' }}">
    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<input type="hidden" name="amount" value="{{ $oldAmount }}">
{{-- Pembanding saja: server menolak kalau biaya sudah berubah sejak halaman dibuka. --}}
<input type="hidden" name="expected_fee" value="{{ $withdrawalFee }}">
<div class="small mt-1 js-fee-preview" data-fee="{{ $withdrawalFee }}" data-min-net="{{ $minNet }}">
    <div>Biaya penarikan: <strong>Rp {{ number_format($withdrawalFee, 0, ',', '.') }}</strong></div>
    <div>Diterima di rekening: <strong class="js-fee-net">-</strong></div>
    <div class="text-muted">Minimal penarikan Rp {{ number_format($minAmount, 0, ',', '.') }}</div>
</div>
@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function withDots(digits) {
                return digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            }

            document.querySelectorAll('.js-fee-preview').forEach(function (box) {
                var form = box.closest('form');
                var display = form.querySelector('.js-amount-display');
                var hidden = form.querySelector('input[name="amount"]');
                var out = box.querySelector('.js-fee-net');
                var fee = parseInt(box.dataset.fee, 10) || 0;
                var minNet = parseInt(box.dataset.minNet, 10) || 0;

                function render() {
                    var amount = parseInt(hidden.value, 10);
                    if (!amount) { out.textContent = '-'; out.className = 'js-fee-net'; return; }
                    var net = amount - fee;
                    out.textContent = 'Rp ' + withDots(String(Math.max(0, net)));
                    out.className = 'js-fee-net' + (net < minNet ? ' text-danger' : '');
                }

                // Format ulang tiap ketikan; posisi kursor dijaga berdasarkan
                // jumlah digit di depannya, supaya titik tidak membuat kursor loncat.
                display.addEventListener('input', function () {
                    var before = display.value.slice(0, display.selectionStart).replace(/\D/g, '').length;
                    var digits = display.value.replace(/\D/g, '').replace(/^0+(?=\d)/, '').slice(0, 15);
                    var formatted = withDots(digits);
                    var pos = 0;
                    for (var seen = 0; pos < formatted.length && seen < before; pos++) {
                        if (formatted[pos] !== '.') { seen++; }
                    }
                    display.value = formatted;
                    hidden.value = digits;
                    display.setSelectionRange(pos, pos);
                    render();
                });

                render();
            });
        });
    </script>
@endonce
