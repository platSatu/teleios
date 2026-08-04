@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Detail Deposit — {{ $deposit->reference_number }}</h4>
        <a href="{{ route('deposits.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-3">Informasi Deposit</h5>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 40%">User</td>
                            <td>{{ $deposit->user->name ?? '-' }} ({{ $deposit->user->email ?? '-' }})</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Nominal</td>
                            <td>Rp {{ number_format($deposit->amount, 0, ',', '.') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Metode</td>
                            <td>{{ $deposit->payment_method ?? '-' }} / {{ $deposit->payment_provider ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>
                                <span class="badge
                                    @if($deposit->status === 'SUCCESS') bg-success-subtle text-success
                                    @elseif($deposit->status === 'PENDING') bg-warning-subtle text-warning
                                    @else bg-danger-subtle text-danger
                                    @endif">
                                    {{ $deposit->status }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibuat</td>
                            <td>{{ $deposit->created_at->format('d M Y H:i') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibayar</td>
                            <td>{{ optional($deposit->paid_at)->format('d M Y H:i') ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Riwayat Perubahan Status</h5>
                    @forelse ($statusHistory as $history)
                        <div class="d-flex justify-content-between border-bottom py-2">
                            <div>
                                <span class="text-muted">{{ $history->old_status ?? '—' }}</span>
                                <i class="ri-arrow-right-line mx-1"></i>
                                <span class="fw-semibold">{{ $history->new_status }}</span>
                            </div>
                            <div class="text-muted small">{{ $history->created_at->format('d M Y H:i') }}</div>
                        </div>
                    @empty
                        <p class="text-muted mb-0">Belum ada riwayat status.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="mb-3">History Saldo Sebelum &amp; Sesudah (Ledger)</h5>

                    @if ($deposit->ledgerTransaction)
                        <p class="text-muted mb-2">
                            Transaksi ledger: <span class="fw-semibold">{{ $deposit->ledgerTransaction->transaction_number }}</span>
                            ({{ $deposit->ledgerTransaction->status }})
                        </p>

                        <div class="table-responsive">
                            <table class="table table-sm table-centered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Arah</th>
                                        <th>Nominal</th>
                                        <th>Saldo Sebelum</th>
                                        <th>Saldo Sesudah</th>
                                        <th>Waktu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($deposit->ledgerTransaction->entries as $entry)
                                        <tr>
                                            <td>
                                                <span class="badge {{ $entry->direction === 'CREDIT' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                    {{ $entry->direction }}
                                                </span>
                                            </td>
                                            <td>Rp {{ number_format($entry->amount, 0, ',', '.') }}</td>
                                            <td>Rp {{ number_format($entry->balance_before, 0, ',', '.') }}</td>
                                            <td>Rp {{ number_format($entry->balance_after, 0, ',', '.') }}</td>
                                            <td>{{ $entry->created_at->format('d M Y H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">Belum ada entri ledger.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">Deposit ini belum menghasilkan transaksi ledger (masih PENDING).</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="mb-3">Payment Transaction</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Provider</th>
                                    <th>Status</th>
                                    <th>Nominal</th>
                                    <th>Callback</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($paymentTransactions as $pt)
                                    <tr>
                                        <td>{{ $pt->provider }}</td>
                                        <td>{{ $pt->status }}</td>
                                        <td>Rp {{ number_format($pt->amount, 0, ',', '.') }}</td>
                                        <td>{{ optional($pt->callback_received_at)->format('d M Y H:i') ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Belum ada payment transaction.</td>
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
