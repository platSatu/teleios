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
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div>
                        <div class="text-muted small">Status Kategori</div>
                        <span class="badge {{ $category->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $category->status }}</span>
                    </div>
                    <div>
                        <div class="text-muted small">Review</div>
                        @if ($category->review_status === 'approved')
                            <span class="badge bg-success-subtle text-success">Approved</span>
                        @elseif ($category->review_status === 'rejected')
                            <span class="badge bg-danger-subtle text-danger">Rejected</span>
                        @else
                            <span class="badge bg-warning-subtle text-warning">Pending</span>
                        @endif
                    </div>
                    @if ($category->review_status === 'rejected' && $category->rejection_reason)
                        <div>
                            <div class="text-muted small">Alasan</div>
                            <div class="small">{{ $category->rejection_reason }}</div>
                        </div>
                    @endif
                </div>
                {{-- Buttons kept as direct children of .btn-group, forms
                     moved out (hidden) + linked via form="..." — see
                     index.blade.php's comment for why nesting a <button>
                     inside its own <form> breaks Bootstrap's btn-group
                     fusing/alignment. --}}
                <div class="btn-group btn-group-sm">
                    @if ($category->review_status !== 'approved')
                        <button type="submit" form="approve-category" class="btn btn-outline-success">
                            <i class="ri-check-line"></i> Setujui Kategori
                        </button>
                    @endif
                    @if ($category->review_status !== 'rejected')
                        <button type="submit" form="reject-category" class="btn btn-outline-danger">
                            <i class="ri-close-line"></i> Tolak Kategori
                        </button>
                    @endif
                </div>
                @if ($category->review_status !== 'approved')
                    <form id="approve-category" action="{{ route('wa-templates.categories.approve', $category->id) }}" method="POST" class="d-none js-approve-form" data-confirm-text="Setujui kategori &quot;{{ $category->name }}&quot;?">
                        @csrf
                    </form>
                @endif
                @if ($category->review_status !== 'rejected')
                    <form id="reject-category" action="{{ route('wa-templates.categories.reject', $category->id) }}" method="POST" class="d-none js-reject-form" data-reject-title="Tolak kategori &quot;{{ $category->name }}&quot;?">
                        @csrf
                        <input type="hidden" name="reason">
                    </form>
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
                <table class="table table-centered table-hover align-middle mb-0" style="min-width: 1050px;">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 150px;">Nama</th>
                            <th style="min-width: 90px;">Bahasa</th>
                            <th style="min-width: 220px;">Isi Pesan</th>
                            <th style="min-width: 130px;">Tombol</th>
                            <th style="min-width: 110px;">Status</th>
                            <th style="min-width: 110px;">Review</th>
                            <th class="text-end" style="min-width: 220px;">Aksi</th>
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
                                    @elseif ($template->review_status === 'in_review')
                                        <span class="badge bg-warning-subtle text-warning">In Review</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Draft</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if ($template->review_status !== 'approved')
                                            <button type="submit" form="approve-template-{{ $template->id }}" class="btn btn-outline-success">
                                                <i class="ri-check-line"></i> Setujui
                                            </button>
                                        @endif
                                        @if ($template->review_status !== 'rejected')
                                            <button type="submit" form="reject-template-{{ $template->id }}" class="btn btn-outline-danger">
                                                <i class="ri-close-line"></i> Tolak
                                            </button>
                                        @endif
                                    </div>
                                    @if ($template->review_status !== 'approved')
                                        <form id="approve-template-{{ $template->id }}" action="{{ route('wa-templates.templates.approve', $template->id) }}" method="POST" class="d-none js-approve-form" data-confirm-text="Setujui template &quot;{{ $template->name }}&quot;?">
                                            @csrf
                                        </form>
                                    @endif
                                    @if ($template->review_status !== 'rejected')
                                        <form id="reject-template-{{ $template->id }}" action="{{ route('wa-templates.templates.reject', $template->id) }}" method="POST" class="d-none js-reject-form" data-reject-title="Tolak template &quot;{{ $template->name }}&quot;?">
                                            @csrf
                                            <input type="hidden" name="reason">
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada template di kategori ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $templates->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>

    @include('superadmin.wa-templates._reject-script')
@endsection
