@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Toko Lazada</h4>
                        <p class="text-muted mb-0">Hubungkan toko Lazada ke branch <strong>{{ $branch->name }}</strong>. Pesanan akan tersinkron otomatis setiap 15 menit.</p>
                    </div>
                    @if($isConfigured)
                        <form action="{{ route('marketplace.lazada.shops.connect') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-link"></i> Hubungkan Toko
                            </button>
                        </form>
                    @endif
                </div>

                @unless($isConfigured)
                    <div class="alert alert-warning mb-3">Integrasi Lazada belum diaktifkan. Hubungi admin Bizbos.</div>
                @endunless

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Toko</th>
                                <th>Status</th>
                                <th>Pesanan</th>
                                <th>Sinkron Terakhir</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($shops as $shop)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $shop->name }}</div>
                                        <small class="text-muted">Seller ID {{ $shop->external_shop_id }}</small>
                                    </td>
                                    <td>
                                        @php
                                            $badge = ['active' => 'bg-success-subtle text-success', 'expired' => 'bg-warning-subtle text-warning'][$shop->status] ?? 'bg-secondary-subtle text-secondary';
                                        @endphp
                                        <span class="badge {{ $badge }}">{{ $shop->statusLabel() }}</span>
                                    </td>
                                    <td>{{ number_format($shop->orders_count, 0, ',', '.') }}</td>
                                    <td>
                                        {{ $shop->last_synced_at?->diffForHumans() ?? 'Belum pernah' }}
                                        @if($shop->last_sync_error)
                                            <div class="small text-danger">{{ $shop->last_sync_error }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('marketplace.lazada.orders.index', ['shop' => $shop->id]) }}" class="btn btn-sm btn-light">
                                            <i class="ri-shopping-bag-3-line"></i> Pesanan
                                        </a>
                                        @if($shop->isActive())
                                            <form action="{{ route('marketplace.lazada.shops.sync', $shop->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-light"><i class="ri-refresh-line"></i> Sinkron</button>
                                            </form>
                                            <form action="{{ route('marketplace.lazada.shops.disconnect', $shop->id) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Putuskan toko ini? Riwayat pesanannya tetap tersimpan.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger"><i class="ri-link-unlink"></i> Putuskan</button>
                                            </form>
                                        @elseif($isConfigured)
                                            <form action="{{ route('marketplace.lazada.shops.connect') }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary"><i class="ri-link"></i> Hubungkan Ulang</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        Belum ada toko Lazada yang terhubung ke branch ini.
                                        @if($isConfigured) Klik "Hubungkan Toko", lalu login dan izinkan akses di halaman Lazada. @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
