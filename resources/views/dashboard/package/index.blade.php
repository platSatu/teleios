@extends('layouts.dashboard')

@section('content')
    <style>
        .product-card {
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 .75rem 1.5rem rgba(0, 0, 0, .08) !important;
        }
    </style>

    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h4 class="mb-1">Packages</h4>
            <p class="text-muted mb-0">Pilih paket aplikasi yang sesuai dengan kebutuhan Anda.</p>
        </div>
    </div>

    {{-- Renders the 'error' flash message set by
         App\Http\Middleware\EnsureActivePackage::deny() when a browser
         navigation gets redirected here ("Masa aktif package Anda sudah
         habis..."). This page is that middleware's redirect target
         (route('dashboard.package.index')), so without this include the
         flash was set but never actually rendered anywhere. --}}
    @include('components.notifikasi')

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('dashboard.package.index') }}" class="row g-2 align-items-center">
                <div class="col-12 col-md">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="ri-search-line text-muted"></i>
                        </span>
                        <input type="text" name="search" value="{{ $search }}" class="form-control border-start-0 ps-0"
                            placeholder="Cari nama package...">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">Semua Category Application</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryId === $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ri-filter-3-line"></i> Filter
                    </button>
                </div>
                @if ($search || $categoryId)
                    <div class="col-6 col-md-auto">
                        <a href="{{ route('dashboard.package.index') }}" class="btn btn-outline-secondary w-100">
                            <i class="ri-close-line"></i> Reset
                        </a>
                    </div>
                @endif
            </form>
        </div>
    </div>

    @if ($packages->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="ri-inbox-line fs-1 text-muted d-block mb-3"></i>
                <h5 class="mb-1">Tidak ada package ditemukan</h5>
                <p class="text-muted mb-0">Coba ubah kata kunci pencarian atau filter category.</p>
            </div>
        </div>
    @else
        <div class="row g-4">
            @foreach ($packages as $package)
                <div class="col-12 col-sm-6 col-xl-4">
                    <div class="card h-100 border-0 shadow-sm product-card">
                        <div class="card-body d-flex flex-column p-4">
                            <div class="d-flex align-items-start justify-content-between mb-3">
                                <span class="avatar-item avatar-md rounded-3 bg-primary-subtle text-primary d-flex align-items-center justify-content-center">
                                    <i class="ri-box-3-line fs-4"></i>
                                </span>
                                @if ($package->categoryApplication)
                                    <span class="badge bg-info-subtle text-info fw-medium">
                                        {{ $package->categoryApplication->name }}
                                    </span>
                                @endif
                            </div>

                            <h5 class="mb-2">{{ $package->name }}</h5>
                            <p class="text-muted fs-14 mb-3 flex-grow-1">
                                {{ $package->description ? \Illuminate\Support\Str::limit($package->description, 100) : 'Tidak ada deskripsi.' }}
                            </p>

                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="text-muted fs-13">
                                    <i class="ri-time-line me-1"></i>{{ $package->duration }} hari
                                </span>
                                <h5 class="mb-0 text-primary">Rp {{ number_format($package->price, 0, ',', '.') }}</h5>
                            </div>

                            <a href="{{ route('dashboard.package.checkout', $package->id) }}" class="btn btn-primary w-100">
                                <i class="ri-shopping-cart-2-line"></i> Pilih Package
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $packages->links('pagination::bootstrap-5') }}
            
        </div>
    @endif
@endsection
