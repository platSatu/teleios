@extends('layouts.dashboard')

@section('content')
    @php
        $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
        $shareText = 'Pakai '.config('app.name').' lewat link saya'.($buyerDiscount > 0 ? ' dan dapat diskon '.$rupiah($buyerDiscount) : '').': '.$link;
    @endphp

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h4 class="mb-1">Referral Saya</h4>
            <p class="text-muted mb-0">Bagikan link Anda. Setiap customer yang berlangganan lewat link ini memberi Anda komisi {{ rtrim(rtrim(number_format($commissionPercent, 2, '.', ''), '0'), '.') }}% setiap kali mereka membayar atau perpanjang.</p>
        </div>
        <a href="{{ route('wallet.dashboard.index') }}" class="btn btn-sm btn-outline-secondary"><i class="ri-wallet-3-line"></i> Riwayat Saldo</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger mb-3">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ([
            ['Customer Aktif', $stats['active_customers'].' / '.$stats['customers'], 'ri-store-2-line', 'primary'],
            ['Komisi Tertahan', $rupiah($stats['pending']), 'ri-time-line', 'warning'],
            ['Komisi Sudah Cair', $rupiah($stats['available']), 'ri-hand-coin-line', 'success'],
            ['Diskon untuk Customer Baru', $rupiah($buyerDiscount), 'ri-coupon-3-line', 'info'],
        ] as [$label, $value, $icon, $color])
            <div class="col-6 col-lg-3">
                <div class="card h-100 mb-0">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="avatar-sm rounded-circle bg-{{ $color }}-subtle text-{{ $color }} d-flex align-items-center justify-content-center fs-4" style="width:44px;height:44px;">
                            <i class="{{ $icon }}"></i>
                        </span>
                        <div>
                            <div class="text-muted small">{{ $label }}</div>
                            <div class="fw-semibold fs-16">{{ $value }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <h6 class="mb-3">Link Referral Anda</h6>
                    <div class="input-group mb-2">
                        <span class="input-group-text fw-semibold">{{ $code->code }}</span>
                        <input type="text" class="form-control" id="referral-link" value="{{ $link }}" readonly>
                        <button type="button" class="btn btn-outline-primary" id="referral-link-copy"><i class="ri-file-copy-line"></i> Salin</button>
                    </div>
                    <a href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener" class="btn btn-success btn-sm">
                        <i class="ri-whatsapp-line"></i> Bagikan ke WhatsApp
                    </a>
                    <p class="text-muted small mt-3 mb-0">
                        Komisi dari pembelian pertama customer tertahan {{ $holdDays }} hari (masa komplain), perpanjangan berikutnya langsung masuk saldo.
                        Komisi berhenti otomatis kalau customer berhenti berlangganan.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <h6 class="mb-1">Atur Diskon untuk Customer Baru</h6>
                    <p class="text-muted small mb-3">Diskon tambahan dari Anda, berlaku sekali di pembelian pertama dan dipotong dari komisi Anda. Maksimal {{ $rupiah($maxDiscount) }}.</p>
                    <form action="{{ route('referral.mine.discount') }}" method="POST" class="d-flex gap-2">
                        @csrf
                        @method('PUT')
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" name="buyer_discount_amount" class="form-control" min="0" max="{{ $maxDiscount }}" step="1000"
                                value="{{ old('buyer_discount_amount', (int) $buyerDiscount) }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <h6 class="mb-3">Customer Saya</h6>
            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Customer</th>
                            <th>Paket</th>
                            <th>Bergabung</th>
                            <th>Jatuh Tempo</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            @php
                                $voucher = $customer->vouchers->first(fn ($v) => $v->status === 'active' && $v->valid_until->isFuture()) ?? $customer->vouchers->first();
                                $active = $voucher && $voucher->status === 'active' && $voucher->valid_until->isFuture();
                            @endphp
                            <tr>
                                <td class="fw-semibold">{{ $customer->companies->first()->name ?? $customer->name }}</td>
                                <td>{{ $voucher?->package?->name ?? '-' }}</td>
                                <td class="text-muted small">{{ $customer->created_at->translatedFormat('d M Y') }}</td>
                                <td>
                                    @if ($voucher)
                                        {{ $voucher->valid_until->translatedFormat('d M Y') }}
                                        @if ($active)
                                            <div class="text-muted small">{{ now()->diffInDays($voucher->valid_until) < 1 ? 'hari ini' : (int) now()->diffInDays($voucher->valid_until).' hari lagi' }}</div>
                                        @endif
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                        {{ $active ? 'Aktif' : ($voucher ? 'Berakhir' : 'Belum aktif') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada customer. Yuk bagikan link referral Anda!</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $customers->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h6 class="mb-3">Riwayat Komisi</h6>
            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Customer</th>
                            <th>Paket</th>
                            <th>Diskon Diberikan</th>
                            <th class="text-end">Komisi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usages as $item)
                            <tr>
                                <td class="text-muted small">{{ $item->created_at->translatedFormat('d M Y H:i') }}</td>
                                <td>{{ $item->usedBy->name ?? '-' }}</td>
                                <td>{{ $item->subscription?->package?->name ?? '-' }}</td>
                                <td>{{ $rupiah($item->buyer_discount_amount) }}</td>
                                <td class="text-end fw-semibold">{{ $rupiah($item->commission_amount) }}</td>
                                <td>@include('superadmin.referral-code._status')</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada komisi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $usages->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>

    <script>
        document.getElementById('referral-link-copy')?.addEventListener('click', function () {
            var button = this;
            navigator.clipboard.writeText(document.getElementById('referral-link').value).then(function () {
                button.innerHTML = '<i class="ri-check-line"></i> Tersalin';
                setTimeout(function () { button.innerHTML = '<i class="ri-file-copy-line"></i> Salin'; }, 1500);
            });
        });
    </script>
@endsection
