@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-lg-8">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-3">Edit Kategori Tagihan</h4>

                <form action="{{ route('tagihan.category.update', $category->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('tagihan.category._form')

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('tagihan.category.index') }}" class="btn btn-light">Kembali</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Aturan Denda Bertingkat (tier bebas) pindah ke halaman
             "Setting Tagihan" yang baru, sebagai 3 mode tetap
             (Flat/Persentase/Persentase+Flat) -- lihat
             TagihanCategorySettingController. Sekalian pengingat &
             nomor invoice ada di sana (23 September 2026 redesign). --}}
        <div class="card">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <h5 class="mb-1">Setting Tagihan</h5>
                    <p class="text-muted small mb-0">Atur denda, aturan pengingat, dan format nomor invoice untuk kategori ini.</p>
                </div>
                <a href="{{ route('tagihan.category.setting', $category->id) }}" class="btn btn-outline-primary">
                    <i class="ri-settings-3-line"></i> Buka Setting
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
