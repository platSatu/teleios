@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">FAQ</h4>
                    <p class="text-muted mb-0">Daftar pertanyaan yang sering diajukan.</p>
                </div>
                <a href="{{ route('web.faqs.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah FAQ
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari pertanyaan/jawaban..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pertanyaan</th>
                            <th>Jawaban</th>
                            <th>User</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($faqs as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($item->descriptions), 80) }}</td>
                                <td>{{ $item->user->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('web.faqs.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-faq-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus FAQ ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-faq-{{ $item->id }}" action="{{ route('web.faqs.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada FAQ.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $faqs->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
