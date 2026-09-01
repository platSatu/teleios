@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Template Tanpa Kategori</h4>
            <p class="text-muted mb-0">Dibuat sebelum ada kategori, atau perusahaan sengaja memilih "Tanpa kategori".</p>
        </div>
        <a href="{{ route('wa-templates.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body">
            {{-- min-width per column (not just on the table) is what
                 actually keeps this readable on narrow screens — see
                 resources/views/superadmin/wa-templates/index.blade.php's
                 comment for the full "why". --}}
            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0" style="min-width: 950px;">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 150px;">Nama</th>
                            <th style="min-width: 150px;">Perusahaan</th>
                            <th style="min-width: 220px;">Isi Pesan</th>
                            <th style="min-width: 110px;">Status</th>
                            <th style="min-width: 150px;">Review AI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td class="fw-semibold">{{ $template->name }}</td>
                                <td class="text-muted">{{ $template->company->name ?? '—' }}</td>
                                <td class="text-muted">{{ \Illuminate\Support\Str::limit($template->template, 90) }}</td>
                                <td>
                                    <span class="badge {{ $template->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $template->status }}</span>
                                </td>
                                <td>
                                    @if ($template->review_status === 'approved')
                                        <span class="badge bg-success-subtle text-success">Approved</span>
                                    @elseif ($template->review_status === 'rejected')
                                        <span class="badge bg-danger-subtle text-danger" title="{{ $template->rejection_reason }}">Rejected</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning" title="{{ $template->rejection_reason }}">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Tidak ada template tanpa kategori.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $templates->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
@endsection
