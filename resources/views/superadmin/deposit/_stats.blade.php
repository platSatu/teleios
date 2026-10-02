{{--
    Kartu ringkasan Data Deposit -- semua mengikuti filter tanggal & user
    (App\Services\Finance\FinanceSummaryService). Satu baris per bagian,
    label dibuat singkat supaya cepat dibaca & dicocokkan dengan Duitku.
--}}
@php
    $rp = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $n = fn ($value) => number_format((int) $value, 0, ',', '.');
    $trx = fn ($value) => $n($value).' trx';
    [$dep, $dis, $sales, $ref] = [$stats['deposit'], $stats['disbursement'], $stats['sales'], $stats['referral']];
    $sections = [
        'Deposit' => [
            ['Total', $rp($dep['total']['amount']), 'ri-file-list-3-line', 'primary', $trx($dep['total']['count'])],
            ['Sukses', $rp($dep['success']['amount']), 'ri-wallet-3-line', 'success', $trx($dep['success']['count'])],
            ['Pending', $rp($dep['pending']['amount']), 'ri-time-line', 'warning', $trx($dep['pending']['count'])],
            ['Failed', $rp($dep['failed']['amount']), 'ri-error-warning-line', 'danger', $trx($dep['failed']['count'])],
            ['Expired', $rp($dep['expired']['amount']), 'ri-timer-flash-line', 'secondary', $trx($dep['expired']['count'])],
        ],
        'Disbursement' => [
            ['Total', $rp($dis['total']['amount']), 'ri-bank-line', 'primary', $trx($dis['total']['count'])],
            ['Terkirim', $rp($dis['success']['net']), 'ri-checkbox-circle-line', 'success', $trx($dis['success']['count'])],
            ['Fee', $rp($dis['fee']), 'ri-percent-line', 'info', 'dari yang terkirim'],
            ['Pending', $rp($dis['pending']['amount']), 'ri-time-line', 'warning', $trx($dis['pending']['count'])],
            ['Gagal', $rp($dis['failed']['amount']), 'ri-close-circle-line', 'danger', $trx($dis['failed']['count'])],
        ],
        'Penjualan & Saldo' => [
            ['Penjualan Paket', $rp($sales['amount']), 'ri-shopping-bag-3-line', 'primary', $n($sales['count']).' paket'],
            ['Deposit User', $rp($dep['success']['amount']), 'ri-download-2-line', 'success', 'deposit sukses'],
            ['Sisa Saldo User', $rp($stats['user_balance']), 'ri-safe-2-line', 'secondary', $dateTo && ! $dateTo->isFuture() ? 'per '.$dateTo->translatedFormat('d M Y') : 'per hari ini'],
        ],
        'Referral' => [
            ['Komisi Cair', $rp($ref['paid']), 'ri-hand-coin-line', 'success', null],
            ['Komisi Tertahan', $rp($ref['held']), 'ri-time-line', 'warning', null],
            ['Pembagi Referral', $n($ref['referrers']), 'ri-share-forward-line', 'info', 'user'],
            ['Pemakaian Referral', $n($ref['usages']), 'ri-user-add-line', 'primary', 'kali'],
        ],
    ];
@endphp

@foreach ($sections as $title => $cards)
    <h6 class="text-muted text-uppercase small mb-2">{{ $title }}</h6>
    <div class="row g-2 mb-3">
        @foreach ($cards as [$label, $value, $icon, $tone, $sub])
            @include('superadmin.deposit._stat-card', compact('label', 'value', 'icon', 'tone', 'sub'))
        @endforeach
    </div>
@endforeach
