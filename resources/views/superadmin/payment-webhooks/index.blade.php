@extends('layouts.dashboard')

@section('content')

    <div class="row mb-3">
        <div class="col-md-2 col-6 mb-3 mb-md-0">
            <div class="card mb-0">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Callback</p>
                    <h4 class="mb-0">{{ number_format($counts->sum()) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3 mb-md-0">
            <div class="card mb-0">
                <div class="card-body">
                    <p class="text-muted mb-1">Berhasil</p>
                    <h4 class="mb-0 text-success">{{ number_format($counts->get('PAYMENT_SUCCESS', 0)) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3 mb-md-0">
            <div class="card mb-0">
                <div class="card-body">
                    <p class="text-muted mb-1">Failed</p>
                    <h4 class="mb-0 text-danger">{{ number_format($counts->get('PAYMENT_FAILED', 0)) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3 mb-md-0">
            <div class="card mb-0">
                <div class="card-body">
                    <p class="text-muted mb-1">Expired</p>
                    <h4 class="mb-0 text-secondary">{{ number_format($counts->get('PAYMENT_EXPIRED', 0)) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3 mb-md-0">
            <div class="card mb-0">
                <div class="card-body">
                    <p class="text-muted mb-1">Gagal (Error)</p>
                    <h4 class="mb-0 text-danger">{{ number_format($counts->get('PAYMENT_ERROR', 0)) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-6 mb-3 mb-md-0">
            <div class="card mb-0">
                <div class="card-body">
                    <p class="text-muted mb-1">Pending</p>
                    <h4 class="mb-0 text-warning">{{ number_format($counts->get('PAYMENT_PENDING', 0)) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">Payment Callback Log</h4>
                <p class="text-muted mb-0">
                    Riwayat seluruh callback pembayaran Duitku (berhasil, pending, failed, gagal diproses) ditambah
                    deposit yang kedaluwarsa otomatis — untuk keperluan UAT dan audit.
                </p>
            </div>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-auto">
                    <select name="event_type" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua tipe</option>
                        @foreach ($eventTypes as $type)
                            <option value="{{ $type }}" @selected($activeEventType === $type)>{{ $type }} ({{ number_format($counts->get($type, 0)) }})</option>
                        @endforeach
                    </select>
                </div>
                @if ($activeEventType)
                    <div class="col-auto">
                        <a href="{{ route('payment-webhooks.index') }}" class="btn btn-outline-secondary">
                            <i class="ri-close-line"></i> Reset Filter
                        </a>
                    </div>
                @endif
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tipe</th>
                            <th>Provider</th>
                            <th>Deposit</th>
                            <th>Diterima</th>
                            <th>Diproses</th>
                            <th>Catatan</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $item)
                            <tr>
                                <td>
                                    <span class="badge
                                        @if($item->event_type === 'PAYMENT_SUCCESS') bg-success-subtle text-success
                                        @elseif($item->event_type === 'PAYMENT_PENDING') bg-warning-subtle text-warning
                                        @elseif(in_array($item->event_type, ['PAYMENT_FAILED', 'PAYMENT_ERROR'])) bg-danger-subtle text-danger
                                        @elseif($item->event_type === 'PAYMENT_EXPIRED') bg-secondary-subtle text-secondary
                                        @else bg-info-subtle text-info
                                        @endif">
                                        {{ $item->event_type }}
                                    </span>
                                </td>
                                <td class="text-muted small">{{ $item->provider }}</td>
                                <td class="small">
                                    @if ($item->related_deposit)
                                        <div class="fw-semibold">{{ $item->related_deposit->reference_number }}</div>
                                        <div class="text-muted">Rp {{ number_format($item->related_deposit->amount, 0, ',', '.') }}</div>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $item->created_at->format('d M Y H:i:s') }}</td>
                                <td>
                                    @if ($item->processed)
                                        <span class="badge bg-success-subtle text-success"><i class="ri-checkbox-circle-line"></i> Ya</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Belum</span>
                                    @endif
                                </td>
                                <td class="text-muted small" title="{{ $item->processing_error }}">
                                    {{ \Illuminate\Support\Str::limit($item->processing_error, 50) ?: '-' }}
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('payment-webhooks.show', $item->id) }}" class="btn btn-outline-primary btn-sm" title="Detail JSON">
                                        <i class="ri-code-s-slash-line"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada payment callback log.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $logs->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

@endsection
