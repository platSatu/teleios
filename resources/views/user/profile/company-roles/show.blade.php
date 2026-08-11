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
                    <li class="breadcrumb-item active">{{ $role->name }}</li>
                </ol>
            </nav>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">{{ $role->name }}</h4>
                <p class="text-muted mb-0">Detail role.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('profile.edit', ['tab' => 'roles']) }}" class="btn btn-light">
                    <i class="ri-arrow-left-line"></i> Kembali
                </a>
                <a href="{{ route('profile.company-role-menus.create', $role->id) }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Application
                </a>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="row gy-3">
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
                                <div class="text-muted fs-12">Status</div>
                                <span class="badge {{ $role->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ ucfirst($role->status) }}
                                </span>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Jumlah Menu Aplikasi</div>
                                <div class="fw-semibold">{{ $role->role_menus_count }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted fs-12">Deskripsi</div>
                                <div class="fw-semibold">{{ $role->description ?: '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
