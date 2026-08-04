@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">{{ $role->name }}</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('roles.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-sm table-borderless mb-0">
                <tr>
                    <td class="text-muted" style="width: 25%">Guard</td>
                    <td>{{ $role->guard_name }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Deskripsi</td>
                    <td>{{ $role->description ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="text-muted">Status</td>
                    <td>
                        <span class="badge {{ $role->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                            {{ ucfirst($role->status) }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="text-muted">Dibuat</td>
                    <td>{{ $role->created_at->format('d M Y H:i') }}</td>
                </tr>
            </table>

            <p class="text-muted small mb-0 mt-3">
                Belum ada data user yang terhubung ke role ini — fitur penugasan role lewat tenant belum aktif (tabel <code>tenant_users</code> belum ada migration-nya).
            </p>
        </div>
    </div>
@endsection
