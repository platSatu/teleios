@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Company Role Menus</h4>
                    <p class="text-muted mb-0">Menu aplikasi yang aktif per company, lintas company.</p>
                </div>
                <a href="{{ route('company-role-menu.create') }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Tambah Company Role Menu
                </a>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="table-responsive">
                <table class="table table-centered table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Company</th>
                            <th>Category Application</th>
                            <th>Menu</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($companyRoleMenus as $item)
                            <tr>
                                <td>{{ $item->company->name ?? '-' }}</td>
                                <td>{{ $item->categoryApplication->name ?? '-' }}</td>
                                <td class="fw-semibold">{{ $item->applicationMenu->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('company-role-menu.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-company-role-menu-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Hapus menu ini dari company?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-company-role-menu-{{ $item->id }}" action="{{ route('company-role-menu.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada company role menu.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $companyRoleMenus->links() }}
            </div>
        </div>
    </div>
@endsection
