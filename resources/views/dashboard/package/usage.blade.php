@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">Sisa Kuota Paket Saya</h4>
            <p class="text-muted mb-0">
                @if ($activePackage)
                    Paket aktif: <strong>{{ $activePackage->name }}</strong>
                @else
                    Belum ada paket aktif.
                @endif
            </p>
        </div>
        <a href="{{ route('dashboard.package.index') }}" class="btn btn-outline-primary btn-sm">
            <i class="ri-shopping-cart-2-line"></i> Lihat/Upgrade Paket
        </a>
    </div>

    @include('components.notifikasi')

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @if (empty($rows))
                <p class="text-muted mb-0">Tidak ada batas kuota yang diatur untuk paket Anda saat ini — pemakaian Anda tidak dibatasi.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-centered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fitur</th>
                                <th>Dibeli</th>
                                <th>Terpakai</th>
                                <th>Sisa</th>
                                <th>Periode</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    $percent = $row['used'] !== null && $row['max_value'] > 0
                                        ? min(100, round(($row['used'] / $row['max_value']) * 100))
                                        : null;
                                @endphp
                                <tr>
                                    <td class="fw-semibold">
                                        {{ $row['metric']->name }}
                                        <div class="text-muted small">{{ $row['metric']->description }}</div>
                                    </td>
                                    <td>{{ number_format($row['max_value'], 0, ',', '.') }} {{ $row['metric']->unit }}</td>
                                    <td>
                                        @if ($row['used'] === null)
                                            <span class="text-muted">Tidak diketahui</span>
                                        @else
                                            {{ number_format($row['used'], 0, ',', '.') }} {{ $row['metric']->unit }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($row['remaining'] === null)
                                            <span class="text-muted">-</span>
                                        @else
                                            <span class="badge {{ $row['remaining'] === 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                                                {{ number_format($row['remaining'], 0, ',', '.') }} {{ $row['metric']->unit }}
                                            </span>
                                        @endif
                                        @if ($percent !== null)
                                            <div class="progress mt-1" style="height: 4px;">
                                                <div class="progress-bar {{ $percent >= 100 ? 'bg-danger' : 'bg-primary' }}" style="width: {{ $percent }}%"></div>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-muted small">
                                        @if ($row['period_start'] && $row['period_end'])
                                            {{ $row['period_start']->format('d M Y') }} – {{ $row['period_end']->format('d M Y') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
