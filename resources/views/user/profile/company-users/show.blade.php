@extends('layouts.dashboard')

@section('content')
    <div class="col-12">
        <div class="mb-3">
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('profile.companies.show', $company->id) }}">{{ $company->name }}</a>
                    </li>
                    @if ($branchOffice)
                        <li class="breadcrumb-item">
                            <a href="{{ route('profile.branch-offices.show', $branchOffice->id) }}">{{ $branchOffice->name }}</a>
                        </li>
                    @endif
                    @if ($unit)
                        <li class="breadcrumb-item">
                            <a href="{{ route('profile.branch-office-units.show', $unit->id) }}">{{ $unit->name }}</a>
                        </li>
                    @endif
                    @if ($role)
                        <li class="breadcrumb-item">
                            <a href="{{ route('profile.company-roles.show', $role->id) }}">{{ $role->name }}</a>
                        </li>
                    @endif
                    <li class="breadcrumb-item active">{{ $member->user->name ?? '-' }}</li>
                </ol>
            </nav>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">{{ $member->user->name ?? '-' }}</h4>
                <p class="text-muted mb-0">Detail user.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('profile.edit', ['tab' => 'users']) }}" class="btn btn-light">
                    <i class="ri-arrow-left-line"></i> Kembali
                </a>
                <a href="{{ route('profile.company-users.edit', $member->user_id) }}" class="btn btn-primary">
                    <i class="ri-edit-2-line"></i> Edit
                </a>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row gy-3">
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Nama</div>
                                <div class="fw-semibold">{{ $member->user->name ?? '-' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Email</div>
                                <div class="fw-semibold">{{ $member->user->email ?? '-' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Company</div>
                                <div class="fw-semibold">{{ $company->name }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Branch</div>
                                <div class="fw-semibold">{{ $branchOffice->name ?? '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Unit/Divisi</div>
                                <div class="fw-semibold">{{ $unit->name ?? '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Role</div>
                                <div class="fw-semibold">{{ $role->name ?? '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Status</div>
                                <span class="badge {{ $member->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ ucfirst($member->status) }}
                                </span>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Ditambahkan</div>
                                <div class="fw-semibold">{{ optional($member->created_at)->format('d M Y H:i') }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted fs-12 mb-1">Application</div>
                                @forelse ($memberRows->filter(fn ($row) => $row->categoryApplication) as $memberRow)
                                    <span class="badge bg-info-subtle text-info me-1 mb-1">{{ $memberRow->categoryApplication->name }}</span>
                                @empty
                                    <span class="badge bg-secondary-subtle text-secondary">Semua Akses</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
