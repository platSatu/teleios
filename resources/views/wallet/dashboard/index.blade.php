@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">Riwayat Saldo</h4>
                        <p class="text-muted mb-0">Saldo saat ini: <span class="fw-semibold fs-16">Rp {{ number_format((float) ($wallet->balance ?? 0), 0, ',', '.') }}</span></p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('deposit.topup') }}" class="btn btn-sm btn-primary"><i class="ri-add-line"></i> Top Up</a>
                        <a href="{{ route('dashboard.wallet-transfer.index') }}" class="btn btn-sm btn-outline-secondary"><i class="ri-exchange-line"></i> Transfer Saldo</a>
                        <a href="{{ route('wallet.withdrawal.index') }}" class="btn btn-sm btn-outline-secondary"><i class="ri-bank-line"></i> Tarik Saldo</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-3">Histori Transaksi</h5>
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
                            @forelse(($histori ?? collect()) as $row)
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
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada transaksi saldo.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($histori)
                    <div class="mt-3">
                        {{ $histori->links() }}
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
