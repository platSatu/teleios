@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">History Pemakaian Kode Referral</h4>
                    <p class="text-muted mb-0">Kode referral siapa dipakai oleh user siapa, kapan, dan untuk pembelian package apa.</p>
                </div>
                <a href="{{ route('referral-code.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-arrow-left-line"></i> Kembali
                </a>
            </div>

            <div class="alert alert-success d-flex align-items-center justify-content-between mb-3">
                <span><i class="ri-hand-coin-line me-1"></i> Total Komisi Terbayar</span>
                <strong>Rp {{ number_format($totalCommission, 0, ',', '.') }}</strong>
            </div>

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari kode / nama / email pemakai..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Referral</th>
                            <th>Pemilik Kode (Referrer)</th>
                            <th>Dipakai Oleh</th>
                            <th>Package Dibeli</th>
                            <th>Rate</th>
                            <th>Komisi (Rp)</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usages as $item)
                            <tr>
                                <td><code>{{ $item->referralCode->code ?? '-' }}</code></td>
                                <td>
                                    {{ $item->referralCode->user->name ?? '-' }}
                                    <div class="text-muted small">{{ $item->referralCode->user->email ?? '' }}</div>
                                </td>
                                <td>
                                    {{ $item->usedBy->name ?? '-' }}
                                    <div class="text-muted small">{{ $item->usedBy->email ?? '' }}</div>
                                </td>
                                <td>{{ $item->subscription?->package?->name ?? '-' }}</td>
                                <td>{{ rtrim(rtrim(number_format($item->discount_percent, 2, '.', ''), '0'), '.') }}%</td>
                                <td class="fw-semibold text-success">
                                    @if ($item->commission_amount > 0)
                                        Rp {{ number_format($item->commission_amount, 0, ',', '.') }}
                                    @else
                                        <span class="text-muted fw-normal">Rp 0</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $item->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada pemakaian kode referral.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $usages->links() }}
            </div>
        </div>
    </div>
@endsection
