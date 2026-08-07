@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Company Roles</h4>
                    <p class="text-muted mb-0">Semua role, lintas company.</p>
                </div>
                <a href="{{ route('company-role.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Role
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
                    <input type="text" name="search" class="form-control" placeholder="Cari nama/deskripsi..." value="{{ request('search') }}">
                    <button type="submit" class="btn btn-outline-secondary"><i class="ri-search-line"></i></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama Role</th>
                            <th>Company</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($companyRoles as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->name }}</td>
                                <td>{{ $item->company->name ?? '-' }}</td>
                                <td class="text-muted">{{ \Illuminate\Support\Str::limit($item->description, 60) ?: '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('company-role.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-company-role-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus role ini?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-company-role-{{ $item->id }}" action="{{ route('company-role.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada company role.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $companyRoles->links('pagination::bootstrap-5') }}
                
            </div>
        </div>
    </div>
@endsection
