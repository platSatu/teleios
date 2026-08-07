@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">Ledger Transactions</h4>
                <p class="text-muted mb-0">Header transaksi ledger — setiap satu berisi satu atau lebih ledger entries.</p>
            </div>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto">
                    <input type="text" name="search" class="form-control" placeholder="Cari nomor transaksi..." value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach (['PENDING', 'SUCCESS', 'FAILED'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
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
                            <th>No. Transaksi</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th>Deskripsi</th>
                            <th>Dibuat Oleh</th>
                            <th>Waktu</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->transaction_number }}</td>
                                <td>{{ $item->transaction_type }}</td>
                                <td>
                                    <span class="badge
                                        @if($item->status === 'SUCCESS') bg-success-subtle text-success
                                        @elseif($item->status === 'PENDING') bg-warning-subtle text-warning
                                        @else bg-danger-subtle text-danger
                                        @endif">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td class="text-muted">{{ \Illuminate\Support\Str::limit($item->description, 40) ?: '-' }}</td>
                                <td>{{ $item->creator->name ?? '-' }}</td>
                                <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('ledger-transaction.show', $item->id) }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="ri-eye-line"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada ledger transaction.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $transactions->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
