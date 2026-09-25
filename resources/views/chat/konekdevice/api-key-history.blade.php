@extends('layouts.dashboard')

@section('content')
    @php
        $statusBadge = [
            \App\Models\WaApiRequestLog::STATUS_SENT => 'bg-success-subtle text-success',
            \App\Models\WaApiRequestLog::STATUS_BLOCKED_PACKAGE => 'bg-danger-subtle text-danger',
            \App\Models\WaApiRequestLog::STATUS_BLOCKED_QUOTA => 'bg-warning-subtle text-warning',
            \App\Models\WaApiRequestLog::STATUS_FAILED => 'bg-secondary-subtle text-secondary',
        ];
        $windows = [
            'today' => 'Hari Ini',
            'this_month' => 'Bulan Ini',
            'current_package_period' => 'Periode Paket Aktif',
            'all_time' => 'Total',
        ];
    @endphp

    <div class="row">
        <div class="col-12">
            <div class="d-flex align-items-center gap-2 mb-3">
                <a href="{{ route('chat.connect-device.api-key.show', ['device' => $deviceId, 'phone' => $devicePhone]) }}" class="btn btn-icon btn-outline-secondary btn-sm" title="Kembali ke API Key">
                    <i class="ri-arrow-left-line"></i>
                </a>
                <div>
                    <h4 class="mb-0">Riwayat Pemakaian API</h4>
                    <p class="text-muted mb-0 small">
                        @if ($devicePhone)
                            Device +{{ $devicePhone }}
                        @else
                            Device {{ $deviceId }}
                        @endif
                    </p>
                </div>
            </div>

            @if (! $apiKey)
                <div class="card">
                    <div class="card-body text-center text-muted py-4">
                        Device ini belum punya API Key, jadi belum ada riwayat pemakaian.
                    </div>
                </div>
            @else
                {{-- Paket & kuota --}}
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-12 col-md-6">
                                <p class="text-muted small mb-1">Paket Aktif</p>
                                @if ($summary['package'])
                                    <h5 class="mb-1">
                                        {{ $summary['package']['name'] ?? '-' }}
                                        @if (! empty($summary['package']['branch']))
                                            <span class="text-muted fs-14 fw-normal">&middot; Branch {{ $summary['package']['branch'] }}</span>
                                        @endif
                                    </h5>
                                    <p class="text-muted small mb-0">
                                        Berlaku sampai
                                        {{ \Illuminate\Support\Carbon::parse($summary['package']['valid_until'])->translatedFormat('d M Y H:i') }}
                                    </p>
                                @else
                                    <h5 class="mb-1 text-danger">Tidak ada paket aktif</h5>
                                    <p class="text-muted small mb-0">Pengiriman lewat API akan ditolak (HTTP 403) sampai paket diperpanjang.</p>
                                @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <p class="text-muted small mb-1">Kuota Pengiriman Pesan Bulan Ini</p>
                                @if (! $summary['quota'])
                                    <h5 class="mb-0">-</h5>
                                @elseif ($summary['quota']['unlimited'])
                                    <h5 class="mb-0">Tidak dibatasi</h5>
                                @else
                                    @php
                                        $quota = $summary['quota'];
                                        $percent = $quota['max'] > 0 ? min(100, round($quota['used'] / $quota['max'] * 100)) : 100;
                                    @endphp
                                    <h5 class="mb-1">
                                        {{ number_format($quota['remaining'], 0, ',', '.') }}
                                        <span class="text-muted fs-14 fw-normal">tersisa dari {{ number_format($quota['max'], 0, ',', '.') }}</span>
                                    </h5>
                                    <div class="progress" style="height: 6px;">
                                        <div class="progress-bar {{ $percent >= 90 ? 'bg-danger' : 'bg-primary' }}" role="progressbar" style="width: {{ $percent }}%"></div>
                                    </div>
                                @endif
                                <p class="text-muted fs-12 mb-0 mt-1">
                                    Kuota ini dipakai bersama oleh semua pengiriman branch (API, broadcast, auto-reply) dan di-reset setiap bulan sejak paket aktif.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Jumlah request per jendela waktu --}}
                <div class="row g-3 mb-3">
                    @foreach ($windows as $key => $label)
                        @php $counts = $summary['api_key'][$key]; @endphp
                        <div class="col-6 col-lg-3">
                            <div class="card h-100 mb-0">
                                <div class="card-body">
                                    <p class="text-muted small mb-1">{{ $label }}</p>
                                    @if ($counts === null)
                                        <h4 class="mb-0">-</h4>
                                    @else
                                        <h4 class="mb-1">{{ number_format($counts['sent'], 0, ',', '.') }} <span class="fs-13 text-muted fw-normal">terkirim</span></h4>
                                        <p class="text-muted fs-12 mb-0">
                                            {{ number_format($counts['total'], 0, ',', '.') }} request
                                            @if ($counts['total'] - $counts['sent'] > 0)
                                                &bull; {{ number_format($counts['total'] - $counts['sent'], 0, ',', '.') }} tidak terkirim
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Filter + tabel riwayat --}}
                <div class="card">
                    <div class="card-body">
                        <form method="GET" action="{{ route('chat.connect-device.api-key.history', ['device' => $deviceId]) }}" class="row g-2 align-items-end mb-3">
                            <input type="hidden" name="phone" value="{{ $devicePhone }}">
                            <div class="col-12 col-sm-6 col-md-3">
                                <label class="form-label small mb-1" for="filter-status">Status</label>
                                <select name="status" id="filter-status" class="form-select form-select-sm">
                                    <option value="">Semua status</option>
                                    @foreach ($statusLabels as $value => $label)
                                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-1" for="filter-date-from">Dari Tanggal</label>
                                <input type="date" name="date_from" id="filter-date-from" class="form-control form-control-sm" value="{{ $filters['date_from'] ?? '' }}">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label small mb-1" for="filter-date-to">Sampai Tanggal</label>
                                <input type="date" name="date_to" id="filter-date-to" class="form-control form-control-sm" value="{{ $filters['date_to'] ?? '' }}">
                            </div>
                            <div class="col-12 col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="ri-filter-3-line"></i> Filter</button>
                                <a href="{{ route('chat.connect-device.api-key.history', ['device' => $deviceId, 'phone' => $devicePhone]) }}" class="btn btn-outline-secondary btn-sm flex-fill">Reset</a>
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
                                        <th>Tujuan</th>
                                        <th>Status</th>
                                        <th class="text-center">HTTP</th>
                                        <th class="text-end">Panjang Pesan</th>
                                        <th>Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($logs as $log)
                                        <tr>
                                            <td class="text-nowrap">{{ $log->created_at?->translatedFormat('d M Y H:i:s') }}</td>
                                            <td class="text-nowrap">{{ \Illuminate\Support\Str::before($log->recipient ?? '-', '@s.whatsapp.net') }}</td>
                                            <td>
                                                <span class="badge {{ $statusBadge[$log->status] ?? 'bg-light text-dark' }}">{{ $log->statusLabel() }}</span>
                                            </td>
                                            <td class="text-center">{{ $log->http_status }}</td>
                                            <td class="text-end">{{ $log->message_length !== null ? number_format($log->message_length, 0, ',', '.').' karakter' : '-' }}</td>
                                            <td class="small text-muted" style="min-width: 200px;">
                                                {{ $log->error ?? ($log->wa_message_id ? 'ID pesan: '.$log->wa_message_id : '-') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">Belum ada riwayat request untuk filter ini.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($logs && $logs->hasPages())
                            <div class="mt-3">
                                {{ $logs->links('pagination::bootstrap-5') }}
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
