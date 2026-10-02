{{--
    Tab "Riwayat Saya" (2 Oktober 2026) -- satu daftar tab dipakai di
    halaman History dan di halaman yang dulu jadi menu tersendiri di dropdown
    profil (Referral Saya, Sisa Kuota Saya, Riwayat Saldo). Tiap halaman tetap
    punya controller & query sendiri (data hanya dimuat saat tab-nya dibuka),
    partial ini cuma menyamakan navigasinya.

    Variabel:
    - $active  key tab yang aktif (lihat $tabs di bawah)
    - $counts  [key => jumlah] badge; hanya diisi di halaman History
    Di halaman History, tab bawaan halaman itu ($inPage) berpindah tanpa
    reload (Bootstrap tab); dari halaman lain berupa link ?tab=key.
--}}
@php
    $counts = $counts ?? [];
    $onHistoryPage = request()->routeIs('user-history.index');
    $tabs = [
        'topup' => ['Top Up', 'ri-wallet-3-line', null],
        'voucher' => ['Voucher', 'ri-coupon-3-line', null],
        'subscription' => ['Pembelian Package', 'ri-shopping-bag-3-line', null],
        'transfer' => ['Transfer Saldo', 'ri-exchange-line', null],
        'saldo' => ['Riwayat Saldo', 'ri-history-line', route('wallet.dashboard.index')],
        'kuota' => ['Sisa Kuota', 'ri-pie-chart-2-line', route('dashboard.package.usage')],
        'referral' => ['Referral Saya', 'ri-share-forward-line', route('referral.mine')],
        'login' => ['Login', 'ri-login-circle-line', null],
    ];
@endphp
<div class="mb-3">
    <h5 class="mb-1">Riwayat Saya</h5>
    <p class="text-muted small mb-0">Riwayat transaksi, saldo, kuota paket, referral, dan login akun Anda.</p>
</div>
{{-- Bisa digeser ke samping di layar kecil, label tidak terpotong. --}}
<div class="mb-3" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
    <ul class="nav nav-tabs flex-nowrap mb-0" id="myHistoryTabs" role="tablist" style="width: max-content;">
        @foreach ($tabs as $key => [$label, $icon, $url])
            @php $isActive = $active === $key; @endphp
            <li class="nav-item" role="presentation">
                @if (! $url && $onHistoryPage)
                    <button class="nav-link text-nowrap {{ $isActive ? 'active' : '' }}" data-bs-toggle="tab"
                        data-bs-target="#tab-{{ $key }}" data-tab-key="{{ $key }}" type="button">
                        <i class="{{ $icon }}"></i> {{ $label }}
                        @isset($counts[$key])
                            <span class="badge bg-secondary-subtle text-secondary">{{ $counts[$key] }}</span>
                        @endisset
                    </button>
                @else
                    <a class="nav-link text-nowrap {{ $isActive ? 'active' : '' }}" @if ($isActive) aria-current="page" @endif
                        href="{{ $url ?? route('user-history.index', ['tab' => $key]) }}">
                        <i class="{{ $icon }}"></i> {{ $label }}
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</div>
