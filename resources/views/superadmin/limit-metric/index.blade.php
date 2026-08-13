@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Limit Metric</h4>
                    <p class="text-muted mb-0">Katalog metrik yang bisa diberi batas angka pada sebuah Package (mis. jumlah pengiriman broadcast, jumlah kontak, jumlah device).</p>
                </div>
                <a href="{{ route('limit-metric.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Limit Metric
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari key/nama..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Key</th>
                            <th>Nama</th>
                            <th>Aplikasi</th>
                            <th>Tipe</th>
                            <th>Satuan</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($limitMetrics as $item)
                            <tr>
                                <td><code>{{ $item->key }}</code></td>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td>{{ $item->categoryApplication->name ?? 'Global' }}</td>
                                <td>
                                    <span class="badge {{ $item->metric_type === 'consumable' ? 'bg-info-subtle text-info' : 'bg-warning-subtle text-warning' }}">
                                        {{ ucfirst($item->metric_type) }}
                                    </span>
                                </td>
                                <td>{{ $item->unit ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('limit-metric.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-limit-metric-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus limit metric ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-limit-metric-{{ $item->id }}" action="{{ route('limit-metric.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada limit metric.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $limitMetrics->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
