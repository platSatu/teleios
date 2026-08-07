@extends('layouts.dashboard')

@section('content')

    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-1">Detail Payment Callback</h4>
            <p class="text-muted mb-0">{{ $log->id }}</p>
        </div>
        <a href="{{ route('payment-webhooks.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    <div class="row">
        <div class="col-lg-4 mb-3 mb-lg-0">
            <div class="card">
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted">Tipe</td>
                            <td class="fw-semibold">{{ $log->event_type }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Provider</td>
                            <td>{{ $log->provider }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Diterima</td>
                            <td>{{ $log->created_at->format('d M Y H:i:s') }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Diproses</td>
                            <td>
                                @if ($log->processed)
                                    <span class="badge bg-success-subtle text-success">Ya — {{ optional($log->processed_at)->format('d M Y H:i:s') }}</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Belum</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Catatan</td>
                            <td>{{ $log->processing_error ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Signature</td>
                            <td class="text-break small">{{ $log->signature ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            @if ($log->related_deposit)
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="mb-2">Deposit Terkait</h6>
                        <table class="table table-sm table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Referensi</td>
                                <td class="fw-semibold">{{ $log->related_deposit->reference_number }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Nominal</td>
                                <td>Rp {{ number_format($log->related_deposit->amount, 0, ',', '.') }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status</td>
                                <td>{{ $log->related_deposit->status }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-2">Payload (JSON)</h6>
                    <pre class="bg-light p-3 rounded small mb-0" style="white-space: pre-wrap; word-break: break-word;">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>
        </div>
    </div>

@endsection
