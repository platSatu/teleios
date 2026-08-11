@extends('layouts.dashboard')

@section('content')
    <div class="col-12">
        <div class="mb-3">
            <nav>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="{{ route('profile.companies.show', $company->id) }}">{{ $company->name }}</a>
                    </li>
                    <li class="breadcrumb-item active">{{ $branchOffice->name }}</li>
                </ol>
            </nav>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">{{ $branchOffice->name }}</h4>
                <p class="text-muted mb-0">Detail branch.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('profile.edit', ['tab' => 'branch-office']) }}" class="btn btn-light">
                    <i class="ri-arrow-left-line"></i> Kembali
                </a>
                <a href="{{ route('profile.branch-office-units.create', $branchOffice->id) }}" class="btn btn-primary">
                    <i class="ri-add-line"></i> Add Unit
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
                                <div class="text-muted fs-12">Status</div>
                                <span class="badge {{ $branchOffice->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ ucfirst($branchOffice->status) }}
                                </span>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Slug</div>
                                <div class="fw-semibold">{{ $branchOffice->slug }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Jumlah Unit/Divisi</div>
                                <div class="fw-semibold">{{ $branchOffice->units_count }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted fs-12">Alamat</div>
                                <div class="fw-semibold">{{ $branchOffice->address ?: '—' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted fs-12">Deskripsi</div>
                                <div class="fw-semibold">{{ $branchOffice->description ?: '—' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
