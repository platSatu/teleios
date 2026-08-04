@extends('layouts.dashboard')
@section('content')

    <div class="mb-3">
        <h5 class="mb-1">Riwayat Saya</h5>
        <p class="text-muted small mb-0">Riwayat top up, voucher, dan login akun Anda.</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">

            <ul class="nav nav-tabs mb-3" id="myHistoryTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-topup" type="button">
                                <i class="ri-wallet-3-line"></i> Top Up
                                <span class="badge bg-secondary-subtle text-secondary">{{ $deposits->total() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-voucher" type="button">
                                <i class="ri-coupon-3-line"></i> Voucher
                                <span class="badge bg-secondary-subtle text-secondary">{{ $vouchers->total() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-subscription" type="button">
                                <i class="ri-shopping-bag-3-line"></i> Pembelian Package
                                <span class="badge bg-secondary-subtle text-secondary">{{ $subscriptions->total() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-referral" type="button">
                                <i class="ri-share-forward-line"></i> Kode Referral Saya
                                <span class="badge bg-secondary-subtle text-secondary">{{ $referralUsages?->total() ?? 0 }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-transfer" type="button">
                                <i class="ri-exchange-line"></i> Transfer Saldo
                                <span class="badge bg-secondary-subtle text-secondary">{{ $transfers->total() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-login" type="button">
                                <i class="ri-login-circle-line"></i> Login
                                <span class="badge bg-secondary-subtle text-secondary">{{ $loginHistories->total() }}</span>
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">

                        {{-- Top Up --}}
                        <div class="tab-pane fade show active" id="tab-topup">
                            <div class="d-flex justify-content-end mb-2">
                                <a href="{{ route('deposit.topup') }}" class="btn btn-primary btn-sm">
                                    <i class="ri-add-line"></i> Top Up
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Referensi</th>
                                            <th>Nominal</th>
                                            <th>Status</th>
                                            <th>Tanggal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($deposits as $item)
                                            <tr>
                                                <td class="text-muted small">{{ $item->reference_number }}</td>
                                                <td class="fw-semibold">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                                                <td>
                                                    @if($item->status === 'SUCCESS')
                                                        <span class="badge bg-success-subtle text-success">SUCCESS</span>
                                                    @elseif($item->status === 'PENDING')
                                                        <span class="badge bg-warning-subtle text-warning">PENDING</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger">{{ $item->status }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-muted small">{{ $item->created_at->format('d M Y H:i') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">Belum ada riwayat top up.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                    
                            </div>
                        </div>

                        {{-- Voucher --}}
                        <div class="tab-pane fade" id="tab-voucher">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Kode Voucher</th>
                                            <th>Berlaku Dari</th>
                                            <th>Berlaku Sampai</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($vouchers as $item)
                                            <tr>
                                                <td class="fw-semibold">{{ $item->kode_voucher }}</td>
                                                <td class="text-muted small">{{ optional($item->valid_from)->format('d M Y H:i') }}</td>
                                                <td class="text-muted small">{{ optional($item->valid_until)->format('d M Y H:i') }}</td>
                                                <td>
                                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                        {{ ucfirst($item->status) }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">Belum ada voucher.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                {{ $vouchers->links('pagination::bootstrap-5') }}
                            </div>
                        </div>

                        {{-- Pembelian Package --}}
                        <div class="tab-pane fade" id="tab-subscription">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Package</th>
                                            <th>Total Bayar</th>
                                            <th>Status</th>
                                            <th>Tanggal</th>
                                            <th class="text-end">Invoice</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($subscriptions as $item)
                                            <tr>
                                                <td class="fw-semibold">{{ $item->package?->name ?? ($item->metadata['package_name'] ?? '-') }}</td>
                                                <td>Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                                                <td>
                                                    <span class="badge {{ $item->status === 'ACTIVE' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                                        {{ $item->status }}
                                                    </span>
                                                </td>
                                                <td class="text-muted small">{{ $item->created_at->format('d M Y H:i') }}</td>
                                                <td class="text-end">
                                                    <a href="{{ route('dashboard.package.invoice', $item->id) }}" class="btn btn-outline-secondary btn-sm">
                                                        <i class="ri-file-download-line"></i> Invoice
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">Belum ada riwayat pembelian package.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">{{ $subscriptions->links() }}</div>
                        </div>

                        {{-- Kode Referral Saya --}}
                        <div class="tab-pane fade" id="tab-referral">
                            @if ($referralCode)
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 p-3 border rounded-3 bg-light-subtle">
                                    <div>
                                        <p class="text-muted mb-1 fs-13">Kode Referral Anda</p>
                                        <h4 class="mb-0"><code>{{ $referralCode->code }}</code></h4>
                                        <p class="text-muted fs-13 mb-0">
                                            Bagikan kode ini — setiap orang yang memakainya saat checkout membuat Anda mendapat komisi
                                            {{ rtrim(rtrim(number_format($referralCode->percentage, 2, '.', ''), '0'), '.') }}%
                                            dari setiap pembelian package mereka, selama kode ini aktif.
                                        </p>
                                    </div>
                                    <div class="text-md-end">
                                        <span class="badge {{ $referralCode->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} mb-2 d-inline-block">
                                            {{ $referralCode->status === 'active' ? 'Aktif' : 'Diblokir' }}
                                        </span>
                                        <p class="text-muted mb-1 fs-13">Total Komisi Diterima</p>
                                        <h5 class="mb-0 text-success">Rp {{ number_format($referralTotalCommission, 0, ',', '.') }}</h5>
                                    </div>
                                </div>

                                <h6 class="mb-2 fs-14">Siapa Saja yang Memakai Kode Anda</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Digunakan Oleh</th>
                                                <th>Package Dibeli</th>
                                                <th>Komisi Diterima</th>
                                                <th>Waktu</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($referralUsages as $item)
                                                <tr>
                                                    <td class="fw-semibold">{{ $item->usedBy->name ?? '-' }}</td>
                                                    <td>{{ $item->subscription?->package?->name ?? '-' }}</td>
                                                    <td class="text-success fw-semibold">Rp {{ number_format($item->commission_amount, 0, ',', '.') }}</td>
                                                    <td class="text-muted small">{{ $item->created_at->format('d M Y H:i') }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="text-center text-muted py-4">Belum ada yang memakai kode referral Anda.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    {{ $referralUsages->links('pagination::bootstrap-5') }}
                                </div>
                            @else
                                <p class="text-muted mb-0">Kode referral Anda belum tersedia.</p>
                            @endif
                        </div>

                        {{-- Transfer Saldo --}}
                        <div class="tab-pane fade" id="tab-transfer">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Arah</th>
                                            <th>Lawan Transaksi</th>
                                            <th>Jumlah</th>
                                            <th>Saldo Sebelum</th>
                                            <th>Saldo Sesudah</th>
                                            <th>Catatan</th>
                                            <th>Waktu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($transfers as $item)
                                            @php
                                                $isSender = $item->sender_user_id === auth()->id();
                                            @endphp
                                            <tr>
                                                <td>
                                                    <span class="badge {{ $isSender ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                                                        {{ $isSender ? 'Kirim' : 'Terima' }}
                                                    </span>
                                                </td>
                                                <td>{{ $isSender ? ($item->receiver->name ?? '-') : ($item->sender->name ?? '-') }}</td>
                                                <td class="fw-semibold">Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                                                <td class="text-muted small">
                                                    Rp {{ number_format($isSender ? $item->sender_balance_before : $item->receiver_balance_before, 0, ',', '.') }}
                                                </td>
                                                <td class="text-muted small">
                                                    Rp {{ number_format($isSender ? $item->sender_balance_after : $item->receiver_balance_after, 0, ',', '.') }}
                                                </td>
                                                <td class="text-muted small">{{ $item->note ?: '-' }}</td>
                                                <td class="text-muted small">{{ $item->created_at->format('d M Y H:i') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">Belum ada transfer saldo.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                {{ $transfers->links('pagination::bootstrap-5') }}
                            </div>
                        </div>

                        {{-- Login --}}
                        <div class="tab-pane fade" id="tab-login">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Login</th>
                                            <th>Logout</th>
                                            <th>Durasi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($loginHistories as $item)
                                            <tr>
                                                <td class="text-muted small">{{ optional($item->last_login)->format('d M Y H:i') ?? '-' }}</td>
                                                <td class="text-muted small">
                                                    @if ($item->last_logout)
                                                        {{ $item->last_logout->format('d M Y H:i') }}
                                                    @else
                                                        <span class="badge bg-success-subtle text-success">Sesi aktif</span>
                                                    @endif
                                                </td>
                                                <td class="text-muted small">{{ $item->duration !== null ? gmdate('H:i:s', $item->duration) : '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="3" class="text-center text-muted py-4">Belum ada riwayat login.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                {{ $loginHistories->links('pagination::bootstrap-5') }}
                            </div>
                        </div>

            </div>

        </div>
    </div>
@endsection
