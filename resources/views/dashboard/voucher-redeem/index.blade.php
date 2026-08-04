@extends('layouts.dashboard')

@section('content')
    <div class="mb-4">
        <h4 class="mb-1">Redeem Voucher</h4>
        <p class="text-muted mb-0">Masukkan kode aktivasi dari pembelian package Anda untuk mengaktifkannya.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="mb-3"><i class="ri-coupon-3-line"></i> Redeem Kode</h6>
                    <form action="{{ route('dashboard.voucher-redeem.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="kode_voucher" class="form-label">Kode Voucher</label>
                            <input type="text" name="kode_voucher" id="kode_voucher" class="form-control text-uppercase"
                                placeholder="Contoh: A1B2C3D4E5" value="{{ old('kode_voucher') }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="ri-check-double-line"></i> Redeem Sekarang
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-4">
                    <h6 class="mb-3">Menunggu Redeem</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Package</th>
                                    <th>Durasi</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pendingVouchers as $item)
                                    <tr>
                                        <td><code>{{ $item->kode_voucher }}</code></td>
                                        <td>
                                            {{ $item->package?->name ?? '-' }}
                                            <div class="text-muted small">{{ $item->package?->categoryApplication?->name }}</div>
                                        </td>
                                        <td>{{ $item->package?->duration ?? '-' }} hari</td>
                                        <td class="text-end">
                                            <form action="{{ route('dashboard.voucher-redeem.store') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="kode_voucher" value="{{ $item->kode_voucher }}">
                                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                                    <i class="ri-check-line"></i> Redeem
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">Tidak ada voucher yang menunggu redeem.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="mb-3">Sedang Aktif</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Kode</th>
                                    <th>Package</th>
                                    <th>Berlaku Sampai</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($activeVouchers as $item)
                                    <tr>
                                        <td><code>{{ $item->kode_voucher }}</code></td>
                                        <td>{{ $item->package?->name ?? '-' }}</td>
                                        <td>
                                            {{ optional($item->valid_until)->format('d M Y H:i') }}
                                            @if ($item->valid_until && $item->valid_until->isPast())
                                                <span class="badge bg-danger-subtle text-danger ms-1">Expired</span>
                                            @else
                                                <span class="badge bg-success-subtle text-success ms-1">Aktif</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Belum ada voucher aktif.</td>
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
