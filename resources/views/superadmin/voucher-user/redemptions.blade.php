@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">History Pemakaian Voucher</h4>
                    <p class="text-muted mb-0">Siapa memakai kode promo apa, kapan, dan untuk pembelian package apa.</p>
                </div>
                <a href="{{ route('voucher-user.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="ri-arrow-left-line"></i> Kembali ke Voucher
                </a>
            </div>

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari kode voucher / nama / email user..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Voucher</th>
                            <th>Dipakai Oleh</th>
                            <th>Package Dibeli</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($redemptions as $item)
                            <tr>
                                <td>
                                    <code>{{ $item->voucherUser->kode_voucher ?? '-' }}</code>
                                    <div class="text-muted small">{{ $item->voucherUser->name ?? '' }}</div>
                                </td>
                                <td>
                                    {{ $item->user->name ?? '-' }}
                                    <div class="text-muted small">{{ $item->user->email ?? '' }}</div>
                                </td>
                                <td>
                                    {{ $item->subscription?->package?->name ?? '-' }}
                                    <div class="text-muted small">Rp {{ number_format($item->subscription?->amount ?? 0, 0, ',', '.') }}</div>
                                </td>
                                <td class="text-muted small">{{ $item->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Belum ada pemakaian voucher.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $redemptions->links() }}
            </div>
        </div>
    </div>
@endsection
