{{-- Kartu ringkasan Data Deposit -- semua mengikuti filter tanggal & user (FinanceSummaryService). --}}
@php
    $rp = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $n = fn ($value) => number_format((int) $value, 0, ',', '.');
    [$dep, $dis, $sales] = [$stats['deposit'], $stats['disbursement'], $stats['sales']];
@endphp

<h6 class="text-muted text-uppercase small mb-2">Deposit</h6>
<div class="row g-3 mb-4">
    @include('superadmin.deposit._stat-card', ['label' => 'Total Deposit', 'value' => $n($dep['total']), 'icon' => 'ri-file-list-3-line', 'tone' => 'primary', 'sub' => 'semua status'])
    @include('superadmin.deposit._stat-card', ['label' => 'Total Nominal Sukses', 'value' => $rp($dep['success_amount']), 'icon' => 'ri-wallet-3-line', 'tone' => 'success', 'sub' => $n($dep['success']).' transaksi sukses'])
    @include('superadmin.deposit._stat-card', ['label' => 'Pending', 'value' => $n($dep['pending']), 'icon' => 'ri-time-line', 'tone' => 'warning'])
    @include('superadmin.deposit._stat-card', ['label' => 'Gagal / Kedaluwarsa', 'value' => $n($dep['failed'] + $dep['expired']), 'icon' => 'ri-error-warning-line', 'tone' => 'danger', 'sub' => 'Failed '.$n($dep['failed']).' · Expired '.$n($dep['expired'])])
</div>

<h6 class="text-muted text-uppercase small mb-2">Disbursement (Tarik Saldo)</h6>
<div class="row g-3 mb-4">
    @include('superadmin.deposit._stat-card', ['label' => 'Total Disbursement', 'value' => $rp($dis['total_amount']), 'icon' => 'ri-bank-line', 'tone' => 'primary', 'sub' => $n($dis['total']).' pengajuan'])
    @include('superadmin.deposit._stat-card', ['label' => 'Disbursement Sukses', 'value' => $rp($dis['success_net']), 'icon' => 'ri-checkbox-circle-line', 'tone' => 'success', 'sub' => $n($dis['success']).' terkirim ke rekening'])
    @include('superadmin.deposit._stat-card', ['label' => 'Jumlah Fee', 'value' => $rp($dis['fee']), 'icon' => 'ri-percent-line', 'tone' => 'info', 'sub' => 'dari penarikan sukses'])
    @include('superadmin.deposit._stat-card', ['label' => 'Pending / Gagal', 'value' => $n($dis['pending']).' / '.$n($dis['failed']), 'icon' => 'ri-time-line', 'tone' => 'warning', 'sub' => 'gagal = gagal, ditolak, dibatalkan'])
</div>

<h6 class="text-muted text-uppercase small mb-2">Penjualan & Saldo User</h6>
<div class="row g-3 mb-4">
    @include('superadmin.deposit._stat-card', ['label' => 'Total Penjualan Paket', 'value' => $rp($sales['amount']), 'icon' => 'ri-shopping-bag-3-line', 'tone' => 'primary', 'sub' => $n($sales['count']).' paket terjual'])
    @include('superadmin.deposit._stat-card', ['label' => 'Total Deposit User', 'value' => $rp($dep['success_amount']), 'icon' => 'ri-download-2-line', 'tone' => 'success', 'sub' => 'deposit sukses di periode ini'])
    @include('superadmin.deposit._stat-card', ['label' => 'Total Sisa Saldo User', 'value' => $rp($stats['user_balance']), 'icon' => 'ri-safe-2-line', 'tone' => 'secondary', 'sub' => $dateTo && ! $dateTo->isFuture() ? 'per '.$dateTo->translatedFormat('d M Y') : 'per hari ini'])
</div>
