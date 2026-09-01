@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">Visitor Log</h4>
                <p class="text-muted mb-0">Riwayat kunjungan halaman publik fe-konexa (beranda, artikel, syarat &amp; ketentuan, video, kontak).</p>
            </div>

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari halaman / IP / browser..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Halaman</th>
                            <th>Browser</th>
                            <th>OS</th>
                            <th>Device</th>
                            <th>IP</th>
                            <th>Referrer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $item)
                            <tr>
                                <td class="text-nowrap">{{ $item->visited_at->format('d M Y H:i') }}</td>
                                <td>{{ $item->path }}</td>
                                <td>{{ $item->browser ?: '-' }} {{ $item->browser_version }}</td>
                                <td>{{ $item->os ?: '-' }}</td>
                                <td>
                                    <span class="badge bg-info-subtle text-info text-capitalize">{{ $item->device_type ?: '-' }}</span>
                                </td>
                                <td class="text-muted small">{{ $item->ip_address }}</td>
                                <td class="text-muted small">{{ $item->referrer ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada kunjungan tercatat.</td>
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
