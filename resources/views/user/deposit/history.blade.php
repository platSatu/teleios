@extends('layouts.dashboard')
@section('content')

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h5 class="mb-1">Riwayat Deposit</h5>
            <p class="text-muted small mb-0">Seluruh riwayat top up saldo Anda.</p>
        </div>
        <a href="{{ route('deposit.topup') }}" class="btn btn-primary btn-sm">
            <i class="ri-add-line"></i> Top Up
        </a>
    </div>

    <div class="card border-0 shadow-sm">
                <div class="card-body p-0">

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Referensi</th>
                                    <th>Nominal</th>
                                    <th>Status</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($deposits as $deposit)
                                    <tr>
                                        <td class="text-muted small">{{ $deposit->reference_number }}</td>
                                        <td class="fw-semibold">Rp {{ number_format($deposit->amount, 0, ',', '.') }}</td>
                                        <td>
                                            @if($deposit->status === 'PENDING')
                                                <span class="badge bg-warning-subtle text-warning">PENDING</span>
                                            @elseif($deposit->status === 'SUCCESS')
                                                <span class="badge bg-success-subtle text-success">SUCCESS</span>
                                            @else
                                                <span class="badge bg-danger-subtle text-danger">{{ $deposit->status }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">{{ $deposit->created_at->format('d M Y H:i') }}</td>
                                        <td class="text-end">
                                            @if($deposit->status === 'PENDING')
                                                <a href="{{ route('deposit.checkout', $deposit->id) }}" class="btn btn-success btn-sm">
                                                    Lanjutkan Pembayaran
                                                </a>
                                            @elseif($deposit->status === 'SUCCESS')
                                                <span class="text-success small"><i class="ri-checkbox-circle-fill"></i> Selesai</span>
                                            @else
                                                <span class="text-danger small"><i class="ri-close-circle-fill"></i> {{ $deposit->failure_reason ?? 'Gagal' }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5">
                                            <i class="ri-inbox-line fs-2 d-block mb-2"></i>
                                            Belum ada riwayat deposit.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($deposits->hasPages())
                        <div class="card-footer bg-white">
                            {{ $deposits->links() }}
                        </div>
                    @endif

                </div>
            </div>
@endsection
