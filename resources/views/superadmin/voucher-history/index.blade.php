@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">History Vouchers</h4>
                <p class="text-muted mb-0">Jejak setiap voucher dibuat/diubah/dihapus, lengkap dengan data sebelum &amp; sesudah.</p>
            </div>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto">
                    <input type="text" name="search" class="form-control" placeholder="Cari kode voucher..." value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="action" class="form-select">
                        <option value="">Semua aksi</option>
                        @foreach (['CREATE', 'UPDATE', 'DELETE'] as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i> Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode Voucher</th>
                            <th>User Pemilik</th>
                            <th>Aksi</th>
                            <th>Dilakukan Oleh</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($histories as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->voucher->kode_voucher ?? ($item->new_data['kode_voucher'] ?? $item->old_data['kode_voucher'] ?? '-') }}</td>
                                <td>{{ $item->user->name ?? '-' }}</td>
                                <td>
                                    <span class="badge
                                        @if($item->action === 'CREATE') bg-success-subtle text-success
                                        @elseif($item->action === 'UPDATE') bg-warning-subtle text-warning
                                        @else bg-danger-subtle text-danger
                                        @endif">
                                        {{ $item->action }}
                                    </span>
                                </td>
                                <td>{{ $item->actionBy->name ?? '-' }}</td>
                                <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada riwayat voucher.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $histories->links() }}
            </div>
        </div>
    </div>
@endsection
