@extends('layouts.dashboard')

@section('content')
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
            <div>
                <h4 class="mb-1">{{ $company->name }}</h4>
                <p class="text-muted mb-0">Detail company.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('profile.edit', ['tab' => 'company']) }}" class="btn btn-light">
                    <i class="ri-arrow-left-line"></i> Kembali
                </a>
                <a href="{{ route('profile.companies.edit', $company->id) }}" class="btn btn-primary">
                    <i class="ri-edit-line"></i> Edit
                </a>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <img src="{{ $company->logo ? asset('storage/' . $company->logo) : asset('be') . '/assets/images/avatar/avatar-16.jpg' }}"
                                alt="Logo" class="rounded" style="width: 64px; height: 64px; object-fit: cover;">
                            <div>
                                <div class="fw-semibold fs-16">{{ $company->name }}</div>
                                <span class="badge {{ $company->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ ucfirst($company->status) }}
                                </span>
                            </div>
                        </div>

                        <div class="row gy-3">
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Company ID</div>
                                <div class="fw-semibold">{{ $company->company_id }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Slug</div>
                                <div class="fw-semibold">{{ $company->slug }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Telepon</div>
                                <div class="fw-semibold">{{ $company->phone ?: '—' }}</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="text-muted fs-12">Email</div>
                                <div class="fw-semibold">{{ $company->email ?: '—' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted fs-12">Alamat</div>
                                <div class="fw-semibold">{{ $company->address ?: '—' }}</div>
                            </div>
                            <div class="col-12">
                                <div class="text-muted fs-12">Deskripsi</div>
                                <div class="fw-semibold">{{ $company->description ?: '—' }}</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <a href="{{ route('profile.branch-offices.create', $company->id) }}" class="btn btn-primary btn-sm">
                            <i class="ri-add-line"></i> Add Branch
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
