@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Package Limit</h4>
                    <p class="text-muted mb-0">Batas angka tiap Package untuk tiap Limit Metric — mis. Paket A: broadcast_send maks 10.000, Paket B: 15.000.</p>
                </div>
                <a href="{{ route('package-limit.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Package Limit
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Package</th>
                            <th>Aplikasi</th>
                            <th>Metric</th>
                            <th>Batas Maksimal</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($packageLimits as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->package->name ?? '-' }}</td>
                                <td>{{ $item->package->categoryApplication->name ?? '-' }}</td>
                                <td>{{ $item->limitMetric->name ?? '-' }}</td>
                                <td>{{ number_format($item->max_value, 0, ',', '.') }} {{ $item->limitMetric->unit ?? '' }}</td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('package-limit.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-package-limit-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus package limit ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-package-limit-{{ $item->id }}" action="{{ route('package-limit.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada package limit.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $packageLimits->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
