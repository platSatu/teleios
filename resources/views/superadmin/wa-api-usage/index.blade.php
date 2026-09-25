@extends('layouts.dashboard')

@section('content')
    @php
        $totalAll = $totals->sum();
    @endphp

    <div class="row mb-3">
        <div class="col-md-3 col-6 mb-3 mb-md-0">
            <div class="card mb-0">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Request</p>
                    <h4 class="mb-0">{{ number_format($totalAll, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-3 mb-md-0">
            <div class="card mb-0">
                <div class="card-body">
                    <p class="text-muted mb-1">Terkirim</p>
                    <h4 class="mb-0 text-success">{{ number_format($totals->get(\App\Models\WaApiRequestLog::STATUS_SENT, 0), 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card mb-0">
                <div class="card-body">
                    <p class="text-muted mb-1">Ditolak (Paket / Kuota)</p>
                    <h4 class="mb-0 text-warning">
                        {{ number_format($totals->get(\App\Models\WaApiRequestLog::STATUS_BLOCKED_PACKAGE, 0) + $totals->get(\App\Models\WaApiRequestLog::STATUS_BLOCKED_QUOTA, 0), 0, ',', '.') }}
                    </h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card mb-0">
                <div class="card-body">
                    <p class="text-muted mb-1">Gagal Kirim</p>
                    <h4 class="mb-0 text-danger">{{ number_format($totals->get(\App\Models\WaApiRequestLog::STATUS_FAILED, 0), 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">Pemakaian WA API</h4>
                <p class="text-muted mb-0">
                    Rekap pemakaian API pengiriman WhatsApp pihak ketiga per company, periode
                    <strong>{{ $from->translatedFormat('d M Y') }}</strong> s/d <strong>{{ $to->translatedFormat('d M Y') }}</strong>.
                    Klik company untuk melihat riwayat setiap request-nya.
                </p>
            </div>

            <form method="GET" action="{{ route('wa-api-usage.index') }}" class="row g-2 align-items-end mb-3">
                <div class="col-12 col-md-3">
                    <label class="form-label small mb-1" for="filter-company">Company</label>
                    <input type="text" name="company" id="filter-company" class="form-control form-control-sm" placeholder="Cari nama company" value="{{ $filters['company'] ?? '' }}">
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <label class="form-label small mb-1" for="filter-status">Status</label>
                    <select name="status" id="filter-status" class="form-select form-select-sm">
                        <option value="">Semua status</option>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="filter-date-from">Dari</label>
                    <input type="date" name="date_from" id="filter-date-from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? $from->toDateString() }}">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small mb-1" for="filter-date-to">Sampai</label>
                    <input type="date" name="date_to" id="filter-date-to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? $to->toDateString() }}">
                </div>
                <div class="col-12 col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="ri-filter-3-line"></i> Filter</button>
                    <a href="{{ route('wa-api-usage.index') }}" class="btn btn-outline-secondary btn-sm flex-fill">Reset</a>
                </div>
            </form>

            @if ($errors->any())
                <div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Company</th>
                            <th class="text-end">Terkirim</th>
                            <th class="text-end">Ditolak Kuota</th>
                            <th class="text-end">Ditolak Paket</th>
                            <th class="text-end">Gagal</th>
                            <th class="text-end">Total</th>
                            <th>Request Terakhir</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $company = $companies->get($row->company_id); @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $company?->name ?? '(company sudah dihapus)' }}</div>
                                    <div class="text-muted small">{{ $company?->email ?? $company?->phone ?? '' }}</div>
                                </td>
                                <td class="text-end text-success fw-semibold">{{ number_format($row->sent, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row->blocked_quota, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row->blocked_package, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row->failed, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row->total, 0, ',', '.') }}</td>
                                <td class="text-muted small text-nowrap">{{ \Illuminate\Support\Carbon::parse($row->last_request_at)->translatedFormat('d M Y H:i') }}</td>
                                <td class="text-end">
                                    @if ($company)
                                        <a href="{{ route('wa-api-usage.show', ['company' => $company->id, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="ri-eye-line"></i> Detail
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">Belum ada pemakaian WA API untuk filter ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="mt-3">
                    {{ $rows->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </div>
@endsection
