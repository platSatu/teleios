@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Header</h4>
                    <p class="text-muted mb-0">Daftar slide header/hero homepage.</p>
                </div>
                <a href="{{ route('web.headers.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Header
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari headline/deskripsi..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Preview</th>
                            <th>Headline</th>
                            <th>Background</th>
                            <th>Tombol</th>
                            <th>Urutan</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($headers as $item)
                            <tr>
                                <td>
                                    @if ($item->background_type === 'video' && $item->thumbnail_images)
                                        <img src="{{ $item->thumbnail_images_url }}" alt="{{ $item->text }}" style="width: 96px; height: 54px; object-fit: cover;" class="rounded border">
                                    @elseif ($item->background_type === 'image' && $item->background_images)
                                        <img src="{{ $item->background_images_url }}" alt="{{ $item->text }}" style="width: 96px; height: 54px; object-fit: cover;" class="rounded border">
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="fw-semibold">{{ $item->text ?: '-' }}</td>
                                <td>
                                    @if ($item->background_type === 'video')
                                        <span class="badge bg-danger-subtle text-danger">Video</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Gambar</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($item->button_action === 'active')
                                        <span class="badge bg-success-subtle text-success">{{ $item->button_text ?: 'Active' }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $item->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('web.headers.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-header-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus header ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-header-{{ $item->id }}" action="{{ route('web.headers.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada header.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $headers->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
