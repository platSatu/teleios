@extends('layouts.dashboard')

@section('content')
    @php
        $statusBadge = [
            \App\Models\WaApiRequestLog::STATUS_SENT => 'bg-success-subtle text-success',
            \App\Models\WaApiRequestLog::STATUS_BLOCKED_PACKAGE => 'bg-danger-subtle text-danger',
            \App\Models\WaApiRequestLog::STATUS_BLOCKED_QUOTA => 'bg-warning-subtle text-warning',
            \App\Models\WaApiRequestLog::STATUS_FAILED => 'bg-secondary-subtle text-secondary',
        ];
    @endphp

    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('wa-api-usage.index', ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]) }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Kembali">
            <i class="ri-arrow-left-line"></i>
        </a>
        <div>
            <h4 class="mb-0">{{ $company->name }}</h4>
            <p class="text-muted mb-0 small">
                {{ collect([$company->email, $company->phone])->filter()->implode(' • ') }}
                &middot; Periode {{ $from->translatedFormat('d M Y') }} s/d {{ $to->translatedFormat('d M Y') }}
            </p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        {{-- Paket & kuota --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <p class="text-muted small mb-1">Paket Aktif</p>
                    @if ($summary['package'])
                        <h5 class="mb-1">{{ $summary['package']['name'] ?? '-' }}</h5>
                        <p class="text-muted small mb-3">
                            {{ \Illuminate\Support\Carbon::parse($summary['package']['valid_from'])->translatedFormat('d M Y') }}
                            s/d {{ \Illuminate\Support\Carbon::parse($summary['package']['valid_until'])->translatedFormat('d M Y H:i') }}
                        </p>
                    @else
                        <h5 class="mb-1 text-danger">Tidak ada paket aktif</h5>
                        <p class="text-muted small mb-3">Semua pengiriman lewat API company ini akan ditolak (HTTP 403).</p>
                    @endif

                    <p class="text-muted small mb-1">Kuota Pengiriman Pesan (semua jalur, bukan hanya API)</p>
                    @if ($summary['quota']['unlimited'])
                        <h5 class="mb-0">Tidak dibatasi</h5>
                    @else
                        @php
                            $quota = $summary['quota'];
                            $percent = $quota['max'] > 0 ? min(100, round($quota['used'] / $quota['max'] * 100)) : 100;
                        @endphp
                        <h5 class="mb-1">
                            {{ number_format($quota['used'], 0, ',', '.') }} / {{ number_format($quota['max'], 0, ',', '.') }}
                            <span class="text-muted fs-14 fw-normal">terpakai &bull; sisa {{ number_format($quota['remaining'], 0, ',', '.') }}</span>
                        </h5>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar {{ $percent >= 90 ? 'bg-danger' : 'bg-primary' }}" role="progressbar" style="width: {{ $percent }}%"></div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Ringkasan status periode ini --}}
        <div class="col-12 col-lg-6">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <p class="text-muted small mb-2">Request API Periode Ini</p>
                    <div class="row g-2">
                        @foreach ($statusLabels as $value => $label)
                            <div class="col-6">
                                <div class="border rounded p-2">
                                    <div class="small text-muted">{{ $label }}</div>
                                    <div class="fs-5 fw-semibold">{{ number_format($totals->get($value, 0), 0, ',', '.') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- API key / device --}}
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="mb-3">Device dengan API Key</h5>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Device</th>
                            <th>Status Key</th>
                            <th class="text-end">Terkirim (periode ini)</th>
                            <th class="text-end">Total Request (periode ini)</th>
                            <th>Terakhir Dipakai</th>
                            <th class="text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($apiKeys as $key)
                            @php $stat = $perKey->get($key->id); @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $key->device_label ? '+'.ltrim($key->device_label, '+') : '-' }}</div>
                                    <div class="text-muted small">{{ $key->device_id }}</div>
                                </td>
                                <td>
                                    <span class="badge {{ $key->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ ucfirst($key->status) }}</span>
                                </td>
                                <td class="text-end">{{ number_format($stat->sent ?? 0, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($stat->total ?? 0, 0, ',', '.') }}</td>
                                <td class="text-muted small">{{ $key->last_used_at?->translatedFormat('d M Y H:i') ?? 'Belum pernah' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('wa-api-usage.show', ['company' => $company->id, 'api_key' => $key->id, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]) }}" class="btn btn-sm btn-outline-secondary">
                                        Lihat riwayat
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Company ini belum pernah membuat API Key.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Riwayat request --}}
    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Riwayat Request</h5>

            <form method="GET" action="{{ route('wa-api-usage.show', ['company' => $company->id]) }}" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label small mb-1" for="filter-api-key">Device</label>
                    <select name="api_key" id="filter-api-key" class="form-select form-select-sm">
                        <option value="">Semua device</option>
                        @foreach ($apiKeys as $key)
                            <option value="{{ $key->id }}" @selected(($filters['api_key'] ?? '') === $key->id)>
                                {{ $key->device_label ? '+'.ltrim($key->device_label, '+') : $key->device_id }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label small mb-1" for="filter-status">Status</label>
                    <select name="status" id="filter-status" class="form-select form-select-sm">
                        <option value="">Semua status</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-md-2">
                    <label class="form-label small mb-1" for="filter-recipient">No. Tujuan</label>
                    <input type="text" name="recipient" id="filter-recipient" class="form-control form-control-sm" placeholder="mis. 62812..." value="{{ $filters['recipient'] ?? '' }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="filter-date-from">Dari</label>
                    <input type="date" name="date_from" id="filter-date-from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? $from->toDateString() }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="filter-date-to">Sampai</label>
                    <input type="date" name="date_to" id="filter-date-to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? $to->toDateString() }}">
                </div>
                <div class="col-12 col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill" title="Filter"><i class="ri-filter-3-line"></i></button>
                    <a href="{{ route('wa-api-usage.show', ['company' => $company->id]) }}" class="btn btn-outline-secondary btn-sm flex-fill" title="Reset"><i class="ri-close-line"></i></a>
                </div>
            </form>

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Device</th>
                            <th>Tujuan</th>
                            <th>Status</th>
                            <th class="text-center">HTTP</th>
                            <th class="text-end">Panjang Pesan</th>
                            <th>Keterangan</th>
                            <th>IP Pengirim</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="text-nowrap">{{ $log->created_at?->translatedFormat('d M Y H:i:s') }}</td>
                                <td class="text-nowrap small">
                                    {{ $log->apiKey?->device_label ? '+'.ltrim($log->apiKey->device_label, '+') : $log->device_id }}
                                </td>
                                <td class="text-nowrap">{{ \Illuminate\Support\Str::before($log->recipient ?? '-', '@s.whatsapp.net') }}</td>
                                <td>
                                    <span class="badge {{ $statusBadge[$log->status] ?? 'bg-light text-dark' }}">{{ $log->statusLabel() }}</span>
                                </td>
                                <td class="text-center">{{ $log->http_status }}</td>
                                <td class="text-end">{{ $log->message_length !== null ? number_format($log->message_length, 0, ',', '.') : '-' }}</td>
                                <td class="small text-muted" style="min-width: 220px;">
                                    {{ $log->error ?? ($log->wa_message_id ? 'ID pesan: '.$log->wa_message_id : '-') }}
                                </td>
                                <td class="small text-muted text-nowrap">{{ $log->ip_address ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Belum ada riwayat request untuk filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="mt-3">
                    {{ $logs->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
