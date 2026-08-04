@extends('layouts.dashboard')

@section('content')
    <div class="row justify-content-center">
        <div class="col-12 col-lg-7 col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5 px-4 px-md-5">

                    <span class="avatar-item avatar-xl rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center mb-4">
                        <i class="ri-shield-cross-line fs-1"></i>
                    </span>

                    <h3 class="mb-2">Masa Aktif Package Anda Sudah Habis</h3>
                    <p class="text-muted mb-4">
                        {{ $message ?? 'Package Anda sudah habis masa aktifnya, silakan beli package kembali untuk melanjutkan aktivitas Anda.' }}
                        Anda tidak dapat mengakses halaman ini sampai memiliki package yang aktif.
                    </p>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2">
                        <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href = '{{ route('dashboard') }}')" class="btn btn-outline-secondary">
                            <i class="ri-arrow-left-line me-1"></i> Kembali
                        </button>
                        <a href="{{ route('dashboard.voucher-redeem.index') }}" class="btn btn-outline-primary">
                            <i class="ri-coupon-3-line me-1"></i> Redeem Voucher
                        </a>
                        <a href="{{ route('dashboard.package.index') }}" class="btn btn-primary">
                            <i class="ri-shopping-cart-2-line me-1"></i> Beli Package
                        </a>
                    </div>

                    <hr class="my-4">

                    <p class="text-muted fs-13 mb-0">
                        Butuh bantuan? Hubungi administrator untuk informasi lebih lanjut mengenai package Anda.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
