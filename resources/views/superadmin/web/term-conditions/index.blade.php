@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Syarat & Ketentuan</h4>
                    <p class="text-muted mb-0">Dikelola di sini, versi berstatus Active tampil di popup halaman Register.</p>
                </div>
                <a href="{{ route('web.term-conditions.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Versi
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari judul/isi..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Judul</th>
                            <th>Isi</th>
                            <th>User</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($terms as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td class="text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($item->descriptions), 80) }}</td>
                                <td>{{ $item->user->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end" style="white-space: nowrap;">
                                    <div class="d-flex flex-nowrap justify-content-end gap-1">
                                        <a href="{{ route('web.term-conditions.edit', $item->id) }}" class="btn btn-sm btn-light" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <button type="submit" form="delete-term-{{ $item->id }}" class="btn btn-sm btn-light text-danger" title="Hapus"
                                            onclick="return confirm('Hapus versi Syarat & Ketentuan ini?');">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </div>
                                    <form id="delete-term-{{ $item->id }}" action="{{ route('web.term-conditions.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada Syarat & Ketentuan.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $terms->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
