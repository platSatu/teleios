@extends('layouts.dashboard')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Company Users</h4>
                    <p class="text-muted mb-0">Keanggotaan user di setiap company, lintas company.</p>
                </div>
                <a href="{{ route('company-to-user.create') }}" class="btn btn-primary">
                    <i class="ri-user-add-line"></i> Tambah Company User
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
                            <th>User</th>
                            <th>Company</th>
                            <th>Role</th>
                            <th>Branch Office / Unit</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($companyToUsers as $item)
                            <tr>
                                <td>
                                    {{ $item->user->name ?? '-' }}
                                    <div class="text-muted small">{{ $item->user->email ?? '-' }}</div>
                                </td>
                                <td>{{ $item->company->name ?? '-' }}</td>
                                <td>{{ $item->role->name ?? '-' }}</td>
                                <td>
                                    @if ($item->branchOffice)
                                        {{ $item->branchOffice->name }}
                                        @if ($item->branchOfficeUnit)
                                            <div class="text-muted small">{{ $item->branchOfficeUnit->name }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $item->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('company-to-user.edit', $item->id) }}" class="btn btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                        <button type="submit" form="delete-company-to-user-{{ $item->id }}" class="btn btn-outline-danger" onclick="return confirm('Keluarkan user ini dari company?');">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    </div>
                                    <form id="delete-company-to-user-{{ $item->id }}" action="{{ route('company-to-user.destroy', $item->id) }}" method="POST" class="d-none">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada company user.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $companyToUsers->links() }}
            </div>
        </div>
    </div>
@endsection
