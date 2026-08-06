@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Artikel</h4>
                    <p class="text-muted mb-0">Daftar artikel web.</p>
                </div>
                <a href="{{ route('web.articles.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Artikel
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
                            <th>Gambar</th>
                            <th>Judul</th>
                            <th>Kategori</th>
                            <th>Dibaca</th>
                            <th>Tanggal Publish</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($articles as $item)
                            <tr>
                                <td>
                                    <img src="{{ $item->images_url }}" alt="{{ $item->title }}" style="width: 56px; height: 56px; object-fit: cover;" class="rounded border">
                                </td>
                                <td class="fw-semibold">{{ $item->title }}</td>
                                <td class="text-muted">{{ $item->category->name ?? '-' }}</td>
                                <td>{{ number_format($item->count_read) }}</td>
                                <td class="text-muted">{{ $item->date_publish?->format('d M Y H:i') ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('web.articles.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-article-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus artikel ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-article-{{ $item->id }}" action="{{ route('web.articles.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada artikel.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $articles->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
