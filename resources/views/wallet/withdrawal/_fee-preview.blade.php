{{--
    Rincian biaya tarik saldo (2 Oktober 2026) -- dipakai form Tarik Saldo
    pribadi & branch. Hanya tampilan; angka sebenarnya dihitung ulang di
    server (WalletWithdrawalService::request()). Taruh di dalam <form> yang
    punya input name="amount". Variabel: $withdrawalFee (int Rupiah).
--}}
@php $minAmount = $withdrawalFee + \App\Services\Wallet\WalletWithdrawalService::MIN_TRANSFER; @endphp
<div class="small mt-1 js-fee-preview" data-fee="{{ $withdrawalFee }}" data-min-net="{{ \App\Services\Wallet\WalletWithdrawalService::MIN_TRANSFER }}">
    <div>Biaya penarikan: <strong>Rp {{ number_format($withdrawalFee, 0, ',', '.') }}</strong></div>
    <div>Diterima di rekening: <strong class="js-fee-net">-</strong></div>
    <div class="text-muted">Minimal penarikan Rp {{ number_format($minAmount, 0, ',', '.') }}</div>
</div>
@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-fee-preview').forEach(function (box) {
                var input = box.closest('form').querySelector('input[name="amount"]');
                var out = box.querySelector('.js-fee-net');
                var fee = parseInt(box.dataset.fee, 10) || 0;
                var minNet = parseInt(box.dataset.minNet, 10) || 0;

                function render() {
                    var amount = parseInt(input.value, 10);
                    if (!amount) { out.textContent = '-'; out.className = 'js-fee-net'; return; }
                    var net = amount - fee;
                    out.textContent = 'Rp ' + Math.max(0, net).toLocaleString('id-ID');
                    out.className = 'js-fee-net' + (net < minNet ? ' text-danger' : '');
                }

                input.min = fee + minNet;
                input.addEventListener('input', render);
                render();
            });
        });
    </script>
@endonce
