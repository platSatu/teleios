@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Artikel Dokumentasi API</h4>
                    <p class="text-muted mb-0">Tampil publik di
                        <a href="{{ url('/dokumentasi') }}" target="_blank" rel="noopener">/dokumentasi</a> — tanpa login.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('wa-api-dokumentasi.categories.index') }}" class="btn btn-outline-secondary">
                        <i class="ri-folder-line"></i> Kategori
                    </a>
                    <a href="{{ route('wa-api-dokumentasi.articles.create') }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah Artikel
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari judul/endpoint..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Method</th>
                            <th>Judul</th>
                            <th>Endpoint</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($articles as $item)
                            <tr>
                                <td><span class="badge bg-secondary-subtle text-secondary">{{ $item->method }}</span></td>
                                <td class="fw-semibold">{{ $item->title }}</td>
                                <td><code class="small">{{ $item->endpoint }}</code></td>
                                <td>{{ $item->categoryDocumentation->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('wa-api-dokumentasi.articles.show', $item->id) }}" class="btn btn-outline-primary">
                                            <i class="ri-eye-line"></i> Show
                                        </a>
                                        <a href="{{ route('wa-api-dokumentasi.articles.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-api-documentation-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus artikel ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-api-documentation-{{ $item->id }}" action="{{ route('wa-api-dokumentasi.articles.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada artikel dokumentasi.</td>
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
