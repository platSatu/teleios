@extends('layouts.dashboard')

@section('content')

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Pending Jobs</p>
                    <h4 class="mb-0">{{ number_format($pendingCount) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Failed Jobs</p>
                    <h4 class="mb-0 {{ $failedCount > 0 ? 'text-danger' : '' }}">{{ number_format($failedCount) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted mb-1">Job Tertua yang Belum Diproses</p>
                    <h4 class="mb-0">{{ $oldestPendingAt?->diffForHumans() ?? '—' }}</h4>
                </div>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="mb-1">Failed Jobs</h4>
                    <p class="text-muted mb-0">Job yang sudah habis percobaan (retry) dan berhenti — ini yang paling perlu diperiksa.</p>
                </div>
                @if ($failedCount > 0)
                    <form method="POST" action="{{ route('queue-monitor.failed.retry-all') }}" onsubmit="return confirm('Coba ulang semua failed job?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="ri-refresh-line"></i> Retry Semua
                        </button>
                    </form>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Job</th>
                            <th>Queue</th>
                            <th>Gagal Pada</th>
                            <th>Error</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($failed as $item)
                            <tr>
                                <td><span class="badge bg-danger-subtle text-danger">{{ $item->job_label }}</span></td>
                                <td class="text-muted small">{{ $item->queue }}</td>
                                <td class="text-muted small">{{ \Illuminate\Support\Carbon::parse($item->failed_at)->format('d M Y H:i') }}</td>
                                <td class="text-muted small" title="{{ $item->exception_summary }}">{{ \Illuminate\Support\Str::limit($item->exception_summary, 70) }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('queue-monitor.failed.retry', $item->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary btn-sm" title="Retry">
                                            <i class="ri-refresh-line"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('queue-monitor.failed.destroy', $item->id) }}" class="d-inline" onsubmit="return confirm('Hapus failed job ini permanen?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Tidak ada failed job. 🎉</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $failed->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="mb-3">
                <h4 class="mb-1">Pending Jobs</h4>
                <p class="text-muted mb-0">Job yang masih menunggu / sedang dikerjakan worker (supervisord <code>teleios-worker</code>).</p>
            </div>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Job</th>
                            <th>Queue</th>
                            <th>Percobaan</th>
                            <th>Status</th>
                            <th>Masuk Antrian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pending as $item)
                            <tr>
                                <td><span class="badge bg-secondary-subtle text-secondary">{{ $item->job_label }}</span></td>
                                <td class="text-muted small">{{ $item->queue }}</td>
                                <td class="text-muted small">{{ $item->attempts }}</td>
                                <td>
                                    @if ($item->is_stale)
                                        <span class="badge bg-warning-subtle text-warning" title="Belum diambil worker selama lebih dari 5 menit — cek apakah worker jalan (supervisorctl status).">
                                            Perlu Dicek
                                        </span>
                                    @elseif ($item->is_reserved)
                                        <span class="badge bg-info-subtle text-info">Sedang Diproses</span>
                                    @else
                                        <span class="badge bg-success-subtle text-success">Menunggu</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $item->queued_at->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Tidak ada job yang sedang mengantre.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $pending->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

@endsection
