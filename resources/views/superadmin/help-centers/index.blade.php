@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Help Center</h4>
                    <p class="text-muted mb-0">Semua tiket komplain/bantuan dari seluruh user.</p>
                </div>
                <a href="{{ route('help-center.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Tiket
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari no. tiket/nama..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>No. Tiket</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>User</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($helpCenters as $index => $item)
                            <tr>
                                <td>{{ $helpCenters->firstItem() + $index }}</td>
                                <td class="fw-semibold">{{ $item->number_ticket }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->category->name ?? '-' }}</td>
                                <td>{{ $item->user->name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-secondary-subtle text-secondary text-capitalize">{{ $item->status }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('help-center.show', $item->id) }}" class="btn btn-outline-primary">
                                            <i class="ri-chat-3-line"></i> Balas
                                        </a>
                                        <a href="{{ route('help-center.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-help-center-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus tiket ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-help-center-{{ $item->id }}" action="{{ route('help-center.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada tiket help center.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $helpCenters->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endsection
