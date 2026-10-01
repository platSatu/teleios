@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Pesanan Lazada</h4>
                        <p class="text-muted mb-0">Pesanan dari toko Lazada di branch <strong>{{ $branch->name }}</strong>. Data hanya untuk dilihat, perubahan pesanan tetap dilakukan di Seller Center Lazada.</p>
                    </div>
                    <a href="{{ route('marketplace.lazada.shops.index') }}" class="btn btn-light">
                        <i class="ri-store-2-line"></i> Kelola Toko
                    </a>
                </div>

                <form method="GET" class="row g-2 mb-3">
                    <div class="col-12 col-md-4">
                        <select name="shop" class="form-select">
                            <option value="">Semua toko</option>
                            @foreach($shops as $shop)
                                <option value="{{ $shop->id }}" @selected(($filters['shop'] ?? null) === $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <select name="status" class="form-select">
                            <option value="">Semua status</option>
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="form-control" placeholder="No. pesanan">
                    </div>
                    <div class="col-12 col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary"><i class="ri-search-line"></i> Cari</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. Pesanan</th>
                                <th>Tanggal</th>
                                <th>Toko</th>
                                <th>Pembeli</th>
                                <th class="text-end">Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $order->order_number ?? $order->external_order_id }}</div>
                                        <small class="text-muted">{{ $order->item_count }} barang{{ $order->payment_method ? ' · '.$order->payment_method : '' }}</small>
                                    </td>
                                    <td class="text-nowrap">{{ $order->ordered_at?->format('d M Y H:i') ?? '-' }}</td>
                                    <td>{{ $order->shop->name ?? '-' }}</td>
                                    <td>{{ $order->buyer_name ?? '-' }}</td>
                                    <td class="text-end text-nowrap">Rp {{ number_format((float) $order->total_amount, 0, ',', '.') }}</td>
                                    <td>
                                        @php
                                            $tone = match ($order->status) {
                                                'delivered', 'confirmed' => 'bg-success-subtle text-success',
                                                'canceled', 'returned', 'failed', 'lost_by_3pl', 'damaged_by_3pl' => 'bg-danger-subtle text-danger',
                                                'unpaid' => 'bg-secondary-subtle text-secondary',
                                                default => 'bg-warning-subtle text-warning',
                                            };
                                        @endphp
                                        <span class="badge {{ $tone }}">{{ $order->statusLabel() }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        @if($shops->isEmpty())
                                            Belum ada toko Lazada di branch ini. <a href="{{ route('marketplace.lazada.shops.index') }}">Hubungkan toko</a> terlebih dahulu.
                                        @else
                                            Belum ada pesanan yang cocok.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $orders->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
