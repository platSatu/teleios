@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Video</h4>
                    <p class="text-muted mb-0">Daftar video web.</p>
                </div>
                <a href="{{ route('web.videos.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Video
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari judul/deskripsi..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Thumbnail</th>
                            <th>Judul</th>
                            <th>Kategori</th>
                            <th>Sumber</th>
                            <th>Dibaca</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($videos as $item)
                            <tr>
                                <td>
                                    @if ($item->thumbnail)
                                        <img src="{{ $item->thumbnail_url }}" alt="{{ $item->title }}" style="width: 96px; height: 54px; object-fit: cover;" class="rounded border">
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="fw-semibold">{{ $item->title }}</td>
                                <td class="text-muted">{{ $item->category->name ?? '-' }}</td>
                                <td>
                                    @if ($item->videos)
                                        <span class="badge bg-secondary-subtle text-secondary">Upload</span>
                                    @endif
                                    @if ($item->link_youtube)
                                        <span class="badge bg-danger-subtle text-danger">YouTube</span>
                                    @endif
                                </td>
                                <td>{{ number_format($item->count_read) }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('web.videos.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-video-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus video ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-video-{{ $item->id }}" action="{{ route('web.videos.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada video.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $videos->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
