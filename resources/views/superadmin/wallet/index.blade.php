@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Wallet</h4>
                    <p class="text-muted mb-0">Saldo wallet seluruh user. Klik Kelola untuk menambah/mengurangi saldo dan melihat history.</p>
                </div>
            </div>

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama / email user..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Currency</th>
                            <th>Saldo</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($wallets as $wallet)
                            <tr>
                                <td>
                                    {{ $wallet->user->name ?? '-' }}
                                    <div class="text-muted small">{{ $wallet->user->email ?? '' }}</div>
                                </td>
                                <td>{{ $wallet->currency }}</td>
                                <td class="fw-semibold">Rp {{ number_format($wallet->balance, 0, ',', '.') }}</td>
                                <td>
                                    <span class="badge {{ strtoupper($wallet->status) === 'ACTIVE' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $wallet->status }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('wallet.history', $wallet->id) }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="ri-history-line"></i> Kelola / History
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada wallet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                 {{ $wallets->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
