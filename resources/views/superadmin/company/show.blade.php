@extends('layouts.dashboard')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">{{ $company->name }}</h4>
            <p class="text-muted mb-0">{{ $company->company_id }} &middot; {{ $company->slug }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('company.edit', $company->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-edit-line"></i> Edit
            </a>
            <a href="{{ route('company.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="mb-3">Detail Company</h6>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted" style="width: 35%">Owner</td>
                            <td>{{ $company->user->name ?? '-' }} <span class="text-muted small">({{ $company->user->email ?? '-' }})</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Deskripsi</td>
                            <td>{{ $company->description ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Alamat</td>
                            <td>{{ $company->address ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Telepon</td>
                            <td>{{ $company->phone ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Email</td>
                            <td>{{ $company->email ?: '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Status</td>
                            <td>
                                <span class="badge {{ $company->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                    {{ ucfirst($company->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted">Dibuat</td>
                            <td>{{ $company->created_at->format('d M Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="mb-0">Roles</h6>
                        <a href="{{ route('company-role.create') }}" class="btn btn-outline-primary btn-sm">
                            <i class="ri-add-line"></i> Tambah Role
                        </a>
                    </div>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($roles as $role)
                                    <tr>
                                        <td>{{ $role->name }}</td>
                                        <td>
                                            <span class="badge {{ $role->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ ucfirst($role->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('company-role.edit', $role->id) }}" class="btn btn-outline-secondary btn-sm">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">Belum ada role.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="mb-0">Users</h6>
                        <a href="{{ route('company-to-user.create') }}" class="btn btn-outline-primary btn-sm">
                            <i class="ri-user-add-line"></i> Tambah User
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($members as $member)
                                    <tr>
                                        <td>{{ $member->user->name ?? '-' }} <span class="text-muted small">({{ $member->user->email ?? '-' }})</span></td>
                                        <td>{{ $member->role->name ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ $member->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ ucfirst($member->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('company-to-user.edit', $member->id) }}" class="btn btn-outline-secondary btn-sm">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">Belum ada user.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0">Menu Aplikasi</h6>
                <a href="{{ route('company-role-menu.create') }}" class="btn btn-outline-primary btn-sm">
                    <i class="ri-add-line"></i> Tambah Menu
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-centered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category Application</th>
                            <th>Menu</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roleMenus as $roleMenu)
                            <tr>
                                <td>{{ $roleMenu->categoryApplication->name ?? '-' }}</td>
                                <td>{{ $roleMenu->applicationMenu->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $roleMenu->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ ucfirst($roleMenu->status) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('company-role-menu.edit', $roleMenu->id) }}" class="btn btn-outline-secondary btn-sm">
                                        <i class="ri-edit-line"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Belum ada menu aplikasi untuk company ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0">Branch Office &amp; Unit/Divisi</h6>
                <div class="d-flex gap-2">
                    <a href="{{ route('branch-office.create') }}" class="btn btn-outline-primary btn-sm">
                        <i class="ri-add-line"></i> Tambah Branch Office
                    </a>
                    <a href="{{ route('branch-office-unit.create') }}" class="btn btn-outline-primary btn-sm">
                        <i class="ri-add-line"></i> Tambah Unit/Divisi
                    </a>
                </div>
            </div>

            @forelse ($branchOffices as $branchOffice)
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <div>
                            <span class="fw-semibold">{{ $branchOffice->name }}</span>
                            <span class="text-muted small">{{ $branchOffice->address ?: '-' }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge {{ $branchOffice->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                {{ ucfirst($branchOffice->status) }}
                            </span>
                            <a href="{{ route('branch-office.edit', $branchOffice->id) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="ri-edit-line"></i>
                            </a>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-centered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Unit/Divisi</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($branchOffice->units as $unit)
                                    <tr>
                                        <td>{{ $unit->name }}</td>
                                        <td>
                                            <span class="badge {{ $unit->status === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                                {{ ucfirst($unit->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('branch-office-unit.edit', $unit->id) }}" class="btn btn-outline-secondary btn-sm">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-2">Belum ada unit/divisi.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="text-muted text-center py-3 mb-0">Belum ada branch office untuk company ini.</p>
            @endforelse
        </div>
    </div>
@endsection
