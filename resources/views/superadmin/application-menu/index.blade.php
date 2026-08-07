@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Application Menus</h4>
                    <p class="text-muted mb-0">Penamaan menu per Category Application.</p>
                </div>
                <a href="{{ route('application-menu.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Application Menu
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <form method="GET" class="mb-3">
                <div class="input-group" style="max-width: 320px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari nama/deskripsi..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Menu</th>
                            <th>Category Application</th>
                            <th>Route Name</th>
                            <th>Deskripsi</th>
                            <th>User</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($applicationMenus as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td>{{ $item->categoryApplication->name ?? '-' }}</td>
                                <td>
                                    @if ($item->route_name)
                                        <code class="fs-12">{{ $item->route_name }}</code>
                                    @else
                                        <span class="text-muted fst-italic">— label saja —</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ \Illuminate\Support\Str::limit($item->description, 60) ?: '-' }}</td>
                                <td>{{ $item->user->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('application-menu.show', $item->id) }}" class="btn btn-outline-primary">
                                            <i class="ri-eye-line"></i> Show
                                        </a>
                                        <a href="{{ route('application-menu.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-application-menu-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus application menu ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-application-menu-{{ $item->id }}" action="{{ route('application-menu.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Belum ada application menu.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $applicationMenus->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
