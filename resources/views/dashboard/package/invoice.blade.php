@extends('layouts.dashboard')

@section('content')
    <style>
        .invoice-card {
            max-width: 720px;
            margin: 0 auto;
        }

        @media print {
            .app-header, #sidebar, .main-breadcrumb, .no-print {
                display: none !important;
            }

            main.app-wrapper {
                margin: 0 !important;
            }

            .invoice-card {
                max-width: 100% !important;
                box-shadow: none !important;
            }
        }
    </style>

    <div class="invoice-card">
        @if (session('success'))
            <div class="alert alert-success no-print">{{ session('success') }}</div>
        @endif

        <div class="d-flex align-items-center justify-content-between mb-3 no-print">
            <h4 class="mb-0">Invoice</h4>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                    <i class="ri-download-2-line"></i> Download / Print PDF
                </button>
                <a href="{{ route('dashboard.package.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-arrow-left-line"></i> Kembali
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <div class="d-flex align-items-start justify-content-between mb-4">
                    <div>
                        <h4 class="mb-1">INVOICE</h4>
                        <p class="text-muted mb-0">#{{ strtoupper(substr($subscription->id, 0, 8)) }}</p>
                    </div>
                    <span class="badge {{ $subscription->status === 'ACTIVE' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} fs-13">
                        {{ $subscription->status }}
                    </span>
                </div>

                <div class="row mb-4">
                    <div class="col-6">
                        <p class="text-muted mb-1 fs-13">Ditagihkan Kepada</p>
                        <p class="fw-semibold mb-0">{{ $subscription->user->name ?? '-' }}</p>
                        <p class="text-muted mb-0 fs-13">{{ $subscription->user->email ?? '-' }}</p>
                    </div>
                    <div class="col-6 text-end">
                        <p class="text-muted mb-1 fs-13">Tanggal</p>
                        <p class="fw-semibold mb-0">{{ $subscription->created_at->format('d M Y H:i') }}</p>
                    </div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-borderless mb-0">
                        <thead class="border-bottom">
                            <tr class="text-muted fs-13">
                                <th>Deskripsi</th>
                                <th class="text-end">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <p class="fw-semibold mb-0">{{ $subscription->package?->name ?? ($subscription->metadata['package_name'] ?? '-') }}</p>
                                    <p class="text-muted fs-13 mb-0">
                                        {{ $subscription->package?->categoryApplication?->name }}
                                        &middot; {{ $subscription->package?->duration ?? '-' }} hari
                                    </p>
                                </td>
                                <td class="text-end">Rp {{ number_format($subscription->metadata['original_price'] ?? $subscription->amount, 0, ',', '.') }}</td>
                            </tr>
                            @if (($subscription->metadata['discount_amount'] ?? 0) > 0)
                                <tr>
                                    <td class="text-muted">
                                        Diskon ({{ rtrim(rtrim(number_format($subscription->metadata['discount_percent'] ?? 0, 2, '.', ''), '0'), '.') }}%)
                                        @if (!empty($subscription->metadata['kode_voucher']))
                                            <span class="badge bg-light text-dark border ms-1">{{ $subscription->metadata['kode_voucher'] }}</span>
                                        @endif
                                        @if (!empty($subscription->metadata['kode_referral']))
                                            <span class="badge bg-light text-dark border ms-1">{{ $subscription->metadata['kode_referral'] }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end text-success">- Rp {{ number_format($subscription->metadata['discount_amount'], 0, ',', '.') }}</td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot class="border-top">
                            <tr>
                                <td class="fw-semibold">Total Dibayar</td>
                                <td class="text-end fw-semibold fs-18 text-primary">Rp {{ number_format($subscription->amount, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="d-flex align-items-center justify-content-between fs-13 text-muted mb-4">
                    <span>Metode Pembayaran</span>
                    <span class="fw-medium text-body">{{ $subscription->paymentTransaction?->payment_method ?? 'WALLET_BALANCE' }}</span>
                </div>

                @if ($subscription->voucher)
                    <div class="alert alert-primary d-flex align-items-center justify-content-between no-print mb-0">
                        <div>
                            <p class="mb-1 fw-semibold">Kode Aktivasi: {{ $subscription->voucher->kode_voucher }}</p>
                            <p class="mb-0 fs-13">Redeem kode ini agar package Anda aktif selama {{ $subscription->package?->duration ?? '-' }} hari.</p>
                        </div>
                        <a href="{{ route('dashboard.voucher-redeem.index') }}" class="btn btn-primary btn-sm text-nowrap">
                            <i class="ri-coupon-3-line"></i> Redeem Sekarang
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
