@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Log Kategori Template WA</h4>
                    <p class="text-muted mb-0">Kategori &amp; template disetujui/ditolak otomatis oleh AI moderasi — halaman ini untuk memantau hasilnya. Klik sebuah kategori untuk melihat template di dalamnya.</p>
                </div>
                <a href="{{ route('wa-templates.uncategorized') }}" class="btn btn-light">
                    Template Tanpa Kategori
                    @if ($uncategorizedCount > 0)
                        <span class="badge bg-warning-subtle text-warning ms-1">{{ $uncategorizedCount }}</span>
                    @endif
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 260px;">
                    <select name="review_status" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Status Review</option>
                        <option value="pending" @selected(request('review_status') == 'pending')>Pending</option>
                        <option value="approved" @selected(request('review_status') == 'approved')>Approved</option>
                        <option value="rejected" @selected(request('review_status') == 'rejected')>Rejected</option>
                    </select>
                </div>
            </form>

            {{-- min-width per column (not just on the table) is what
                 actually keeps this readable on narrow screens: without
                 it, table-responsive doesn't scroll — it squeezes every
                 <td> instead, which is what was cramming the "Aksi"
                 buttons into a squished, oversized-looking stack. --}}
            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0" style="min-width: 900px;">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 170px;">Nama Kategori</th>
                            <th style="min-width: 150px;">Perusahaan</th>
                            <th style="min-width: 140px;">Jumlah Template</th>
                            <th style="min-width: 110px;">Status</th>
                            <th style="min-width: 110px;">Review</th>
                            <th class="text-end" style="min-width: 100px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $category)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('wa-templates.categories.show', $category->id) }}" class="text-body">
                                        {{ $category->name }}
                                    </a>
                                </td>
                                <td class="text-muted">{{ $category->company->name ?? '—' }}</td>
                                <td>{{ $category->templates_count }}</td>
                                <td>
                                    <span class="badge {{ $category->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }} text-capitalize">{{ $category->status }}</span>
                                </td>
                                <td>
                                    @if ($category->review_status === 'approved')
                                        <span class="badge bg-success-subtle text-success">Approved</span>
                                    @elseif ($category->review_status === 'rejected')
                                        <span class="badge bg-danger-subtle text-danger" title="{{ $category->rejection_reason }}">Rejected</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning">Pending</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('wa-templates.categories.show', $category->id) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="ri-eye-line"></i> Lihat
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada kategori template dari perusahaan manapun.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $categories->links('pagination::bootstrap-5') }}</div>
        </div>
    </div>
@endsection
