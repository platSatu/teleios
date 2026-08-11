@extends('layouts.dashboard')

@section('content')
    <div class="col-12">
        <div class="mb-3">
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('profile.companies.show', $company->id) }}">{{ $company->name }}</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('profile.branch-offices.show', $branchOffice->id) }}">{{ $branchOffice->name }}</a>
                    </li>
                    <li class="breadcrumb-item active">{{ $unit->name }}</li>
                </ol>
            </nav>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">{{ $unit->name }}</h4>
                <p class="text-muted mb-0">Detail unit/divisi.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('profile.edit', ['tab' => 'unit-divisi']) }}" class="btn btn-light">
                    <i class="ri-arrow-left-line"></i> Kembali
                </a>
                <a href="{{ route('profile.company-roles.create', $unit->id) }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Role
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
                                <div class="fw-semibold">{{ $branchOffice->name }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Status</div>
                                <span class="badge {{ $unit->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ ucfirst($unit->status) }}
                                </span>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Jumlah Role</div>
                                <div class="fw-semibold">{{ $unit->roles_count }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted fs-12">Deskripsi</div>
                                <div class="fw-semibold">{{ $unit->description ?: '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
