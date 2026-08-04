@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Ledger Transaction — {{ $transaction->transaction_number }}</h4>
        <a href="{{ route('ledger-transaction.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 20%">Tipe</td>
                    <td>{{ $transaction->transaction_type }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Status</td>
                    <td>{{ $transaction->status }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Referensi</td>
                    <td>{{ $transaction->reference_type }} — {{ $transaction->reference_id }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Deskripsi</td>
                    <td>{{ $transaction->description ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Dibuat Oleh</td>
                    <td>{{ $transaction->creator->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Waktu</td>
                    <td>{{ $transaction->created_at->format('d M Y H:i') }}</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Ledger Entries</h5>
            <div class="table-responsive">
                <table class="table table-sm table-centered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Arah</th>
                            <th>Nominal</th>
                            <th>Saldo Sebelum</th>
                            <th>Saldo Sesudah</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transaction->entries as $entry)
                            <tr>
                                <td>{{ $entry->wallet->user->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $entry->direction === 'CREDIT' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $entry->direction }}
                                    </span>
                                </td>
                                <td>Rp {{ number_format($entry->amount, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($entry->balance_before, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($entry->balance_after, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Belum ada entri.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
