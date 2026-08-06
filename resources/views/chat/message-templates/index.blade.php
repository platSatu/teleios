@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-12">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                    <div>
                        <h4 class="mb-1">WA Template</h4>
                        <p class="text-muted mb-0">Simpan pesan yang sering dipakai supaya tinggal dipilih saat membuat Pesan Terjadwal.</p>
                    </div>
                    <a href="{{ route('chat.message-templates.create') }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Template
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table table-centered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:1%">No</th>
                                <th>Nama Template</th>
                                <th>Kategori</th>
                                <th>Isi Pesan</th>
                                <th>Status</th>
                                <th>Review</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $template)
                                <tr>
                                    <td>{{ $loop->iteration + ($templates->currentPage() - 1) * $templates->perPage() }}</td>
                                    <td>{{ $template->name }}</td>
                                    <td class="text-muted">{{ $template->category->name ?? '—' }}</td>
                                    <td class="text-muted text-truncate" style="max-width: 320px;">{{ $template->template }}</td>
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
                                    <td class="text-end" style="white-space: nowrap;">
                                        {{-- Isi Pesan above can wrap the row taller than the other
                                             columns; without this flex wrapper the two action buttons
                                             (block-level by default) stack on top of each other and
                                             drift to the top of the cell instead of staying side by
                                             side and centered — same fix already used on Pesan
                                             Terjadwal's own index for the identical reason. --}}
                                        <div class="d-flex flex-nowrap align-items-center justify-content-end gap-1">
                                            <a href="{{ route('chat.message-templates.edit', $template->id) }}" class="btn btn-sm btn-light" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <form action="{{ route('chat.message-templates.destroy', $template->id) }}" method="POST" onsubmit="return confirm('Hapus template ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-light text-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada template WA. Klik "Tambah Template" untuk membuat yang pertama.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $templates->links('pagination::bootstrap-5') }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
