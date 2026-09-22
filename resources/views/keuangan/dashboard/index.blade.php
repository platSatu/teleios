@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-1">Dashboard Saldo</h4>
                <p class="text-muted mb-0">Ringkasan saldo Branch{{ $context->seesAllBranches() ? ' & total seluruh Company' : '' }}, beserta histori transaksinya.</p>
            </div>
        </div>

        @if($context->seesAllBranches())
            <div class="card mb-3">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Saldo Semua Branch</p>
                    <h3 class="mb-0">Rp {{ number_format($totalSaldo, 0, ',', '.') }}</h3>
                </div>
            </div>
        @endif

        @if($branches->count() > 1)
            <div class="row g-3 mb-3">
                @foreach($branches as $b)
                    @php $w = $wallets->get($b->id); @endphp
                    <div class="col-md-4 col-sm-6">
                        <a href="{{ route('keuangan.dashboard.index', ['branch_office_id' => $b->id]) }}"
                           class="text-decoration-none">
                            <div class="card h-100 {{ $selectedBranch && $selectedBranch->id === $b->id ? 'border-primary' : '' }}">
                                <div class="card-body">
                                    <p class="text-muted mb-1 text-truncate">{{ $b->name }}</p>
                                    <h5 class="mb-0 text-body">Rp {{ number_format((float) ($w->balance ?? 0), 0, ',', '.') }}</h5>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif

        @if($selectedBranch && $selectedWallet)
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="mb-1">Histori Transaksi — {{ $selectedBranch->name }}</h5>
                            <p class="text-muted mb-0">Saldo saat ini: <span class="fw-semibold">Rp {{ number_format((float) $selectedWallet->balance, 0, ',', '.') }}</span></p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="{{ route('keuangan.transfer-fee.index') }}" class="btn btn-sm btn-outline-secondary">Transfer Fee</a>
                            <a href="{{ route('keuangan.withdrawal.index', ['branch_office_id' => $selectedBranch->id]) }}" class="btn btn-sm btn-outline-secondary">Tarik Saldo</a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-centered table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Keterangan</th>
                                    <th>Arah</th>
                                    <th class="text-end">Jumlah</th>
                                    <th class="text-end">Saldo Setelah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($histori as $row)
                                    <tr>
                                        <td>{{ $row->created_at->translatedFormat('d M Y H:i') }}</td>
                                        <td>{{ $row->transaction->description ?? '-' }}</td>
                                        <td>
                                            @if($row->direction === 'CREDIT')
                                                <span class="badge bg-success-subtle text-success">Masuk</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">Keluar</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <span class="{{ $row->direction === 'CREDIT' ? 'text-success' : 'text-danger' }}">
                                                {{ $row->direction === 'CREDIT' ? '+' : '-' }}Rp {{ number_format((float) $row->amount, 0, ',', '.') }}
                                            </span>
                                        </td>
                                        <td class="text-end">Rp {{ number_format((float) $row->balance_after, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">Belum ada transaksi saldo untuk branch ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $histori->links() }}
                    </div>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-body text-center text-muted py-4">
                    Belum ada Branch untuk ditampilkan.
                </div>
            </div>
        @endif

    </div>
</div>
@endsection
