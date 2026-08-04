@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">Admin Wallet Actions</h4>
                <p class="text-muted mb-0">Riwayat penambahan/pengurangan saldo yang dilakukan superadmin, di seluruh user.</p>
            </div>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama / email user..." value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <select name="direction" class="form-select">
                        <option value="">Semua arah</option>
                        <option value="CREDIT" @selected(request('direction') === 'CREDIT')>CREDIT</option>
                        <option value="DEBIT" @selected(request('direction') === 'DEBIT')>DEBIT</option>
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
                            <th>User</th>
                            <th>Arah</th>
                            <th>Nominal</th>
                            <th>Alasan</th>
                            <th>Oleh</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($actions as $item)
                            <tr>
                                <td>{{ $item->wallet->user->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->direction === 'CREDIT' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $item->direction }}
                                    </span>
                                </td>
                                <td>Rp {{ number_format($item->amount, 0, ',', '.') }}</td>
                                <td class="text-muted">{{ \Illuminate\Support\Str::limit($item->reason, 50) ?: '-' }}</td>
                                <td>{{ $item->admin->name ?? '-' }}</td>
                                <td>{{ $item->created_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada aksi admin.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                 {{ $actions->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
