@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">Payment Transactions</h4>
                <p class="text-muted mb-0">Catatan transaksi payment gateway (saat ini: simulasi manual deposit).</p>
            </div>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto">
                    <select name="status" class="form-select">
                        <option value="">Semua status</option>
                        @foreach (['PENDING', 'SUCCESS', 'FAILED'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <input type="text" name="provider" class="form-control" placeholder="Provider..." value="{{ request('provider') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i> Filter</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Referensi</th>
                            <th>User</th>
                            <th>Provider</th>
                            <th>Metode</th>
                            <th>Nominal</th>
                            <th>Status</th>
                            <th>Callback</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->reference_type }} — {{ \Illuminate\Support\Str::limit($item->reference_id, 8, '') }}</td>
                                <td>{{ $item->reference->user->name ?? '-' }}</td>
                                <td>{{ $item->provider ?? '-' }}</td>
                                <td>{{ $item->payment_method ?? '-' }}</td>
                                <td>Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                                <td>
                                    <span class="badge
                                        @if($item->status === 'SUCCESS') bg-success-subtle text-success
                                        @elseif($item->status === 'PENDING') bg-warning-subtle text-warning
                                        @else bg-danger-subtle text-danger
                                        @endif">
                                        {{ $item->status }}
                                    </span>
                                </td>
                                <td>{{ optional($item->callback_received_at)->format('d M Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada payment transaction.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $transactions->links() }}
            </div>
        </div>
    </div>
@endsection
