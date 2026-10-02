{{-- Kartu ringkasan Data Deposit -- semua mengikuti filter tanggal & user (FinanceSummaryService). --}}
@php
    $rp = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $n = fn ($value) => number_format((int) $value, 0, ',', '.');
    [$dep, $dis, $sales] = [$stats['deposit'], $stats['disbursement'], $stats['sales']];
@endphp

{{-- 5 kartu sejajar di layar lebar (col-xl), jumlah transaksi + nominal per status untuk dicocokkan dengan dashboard Duitku. --}}
@php $five = 'col-12 col-sm-6 col-xl'; @endphp
<h6 class="text-muted text-uppercase small mb-2">Deposit</h6>
<div class="row g-3 mb-4">
    @include('superadmin.deposit._stat-card', ['col' => $five, 'label' => 'Total Deposit', 'value' => $rp($dep['total']['amount']), 'icon' => 'ri-file-list-3-line', 'tone' => 'primary', 'sub' => $n($dep['total']['count']).' transaksi, semua status'])
    @include('superadmin.deposit._stat-card', ['col' => $five, 'label' => 'Sukses', 'value' => $rp($dep['success']['amount']), 'icon' => 'ri-wallet-3-line', 'tone' => 'success', 'sub' => $n($dep['success']['count']).' transaksi'])
    @include('superadmin.deposit._stat-card', ['col' => $five, 'label' => 'Pending', 'value' => $rp($dep['pending']['amount']), 'icon' => 'ri-time-line', 'tone' => 'warning', 'sub' => $n($dep['pending']['count']).' transaksi'])
    @include('superadmin.deposit._stat-card', ['col' => $five, 'label' => 'Failed', 'value' => $rp($dep['failed']['amount']), 'icon' => 'ri-error-warning-line', 'tone' => 'danger', 'sub' => $n($dep['failed']['count']).' transaksi'])
    @include('superadmin.deposit._stat-card', ['col' => $five, 'label' => 'Expired', 'value' => $rp($dep['expired']['amount']), 'icon' => 'ri-timer-flash-line', 'tone' => 'secondary', 'sub' => $n($dep['expired']['count']).' transaksi'])
</div>

<h6 class="text-muted text-uppercase small mb-2">Disbursement (Tarik Saldo)</h6>
<div class="row g-3 mb-4">
    @include('superadmin.deposit._stat-card', ['col' => $five, 'label' => 'Total Disbursement', 'value' => $rp($dis['total']['amount']), 'icon' => 'ri-bank-line', 'tone' => 'primary', 'sub' => $n($dis['total']['count']).' pengajuan, semua status'])
    @include('superadmin.deposit._stat-card', ['col' => $five, 'label' => 'Sukses (dikirim ke rekening)', 'value' => $rp($dis['success']['net']), 'icon' => 'ri-checkbox-circle-line', 'tone' => 'success', 'sub' => $n($dis['success']['count']).' transaksi · saldo dipotong '.$rp($dis['success']['amount'])])
    @include('superadmin.deposit._stat-card', ['col' => $five, 'label' => 'Jumlah Fee', 'value' => $rp($dis['fee']), 'icon' => 'ri-percent-line', 'tone' => 'info', 'sub' => 'dari penarikan sukses'])
    @include('superadmin.deposit._stat-card', ['col' => $five, 'label' => 'Pending', 'value' => $rp($dis['pending']['amount']), 'icon' => 'ri-time-line', 'tone' => 'warning', 'sub' => $n($dis['pending']['count']).' pengajuan (menunggu / diproses / dicek)'])
    @include('superadmin.deposit._stat-card', ['col' => $five, 'label' => 'Gagal', 'value' => $rp($dis['failed']['amount']), 'icon' => 'ri-close-circle-line', 'tone' => 'danger', 'sub' => $n($dis['failed']['count']).' pengajuan (gagal / ditolak / dibatalkan)'])
</div>

<h6 class="text-muted text-uppercase small mb-2">Penjualan & Saldo User</h6>
<div class="row g-3 mb-4">
    @include('superadmin.deposit._stat-card', ['label' => 'Total Penjualan Paket', 'value' => $rp($sales['amount']), 'icon' => 'ri-shopping-bag-3-line', 'tone' => 'primary', 'sub' => $n($sales['count']).' paket terjual'])
    @include('superadmin.deposit._stat-card', ['label' => 'Total Deposit User', 'value' => $rp($dep['success']['amount']), 'icon' => 'ri-download-2-line', 'tone' => 'success', 'sub' => 'deposit sukses di periode ini'])
    @include('superadmin.deposit._stat-card', ['label' => 'Total Sisa Saldo User', 'value' => $rp($stats['user_balance']), 'icon' => 'ri-safe-2-line', 'tone' => 'secondary', 'sub' => $dateTo && ! $dateTo->isFuture() ? 'per '.$dateTo->translatedFormat('d M Y') : 'per hari ini'])
</div>
