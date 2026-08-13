@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ $category->name }}</h4>
            <p class="text-muted mb-0">
                Perusahaan: <strong>{{ $category->company->name ?? '—' }}</strong>
            </p>
        </div>
        <a href="{{ route('wa-templates.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div>
                    <div class="text-muted small">Status Kategori</div>
                    <span class="badge {{ $category->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $category->status }}</span>
                </div>
                <div>
                    <div class="text-muted small">Review AI</div>
                    @if ($category->review_status === 'approved')
                        <span class="badge bg-success-subtle text-success">Approved</span>
                    @elseif ($category->review_status === 'rejected')
                        <span class="badge bg-danger-subtle text-danger">Rejected</span>
                    @else
                        <span class="badge bg-warning-subtle text-warning">Pending</span>
                    @endif
                </div>
                @if ($category->rejection_reason)
                    <div>
                        <div class="text-muted small">Catatan AI</div>
                        <div class="small">{{ $category->rejection_reason }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Template dalam Kategori Ini</h5>

            {{-- min-width per column (not just on the table) is what
                 actually keeps this readable on narrow screens — see
                 resources/views/superadmin/wa-templates/index.blade.php's
                 comment for the full "why". --}}
            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0" style="min-width: 900px;">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 150px;">Nama</th>
                            <th style="min-width: 90px;">Bahasa</th>
                            <th style="min-width: 220px;">Isi Pesan</th>
                            <th style="min-width: 130px;">Tombol</th>
                            <th style="min-width: 110px;">Status</th>
                            <th style="min-width: 150px;">Review AI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td class="fw-semibold">{{ $template->name }}</td>
                                <td class="text-uppercase text-muted small">{{ $template->language }}</td>
                                <td class="text-muted" style="max-width:280px">
                                    @if ($template->header)
                                        <div class="fw-semibold small">{{ $template->header }}</div>
                                    @endif
                                    <div>{{ \Illuminate\Support\Str::limit($template->template, 100) }}</div>
                                    @if ($template->footer)
                                        <div class="text-muted small">{{ $template->footer }}</div>
                                    @endif
                                </td>
                                <td>
                                    @foreach ($template->buttons ?? [] as $button)
                                        <span class="badge bg-light text-dark border d-inline-block mb-1">{{ $button['label'] ?? '' }}</span>
                                    @endforeach
                                </td>
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
                                <td colspan="6" class="text-center text-muted py-4">Belum ada template di kategori ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $templates->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
@endsection
