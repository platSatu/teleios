@extends('layouts.dashboard')

@section('content')
<div class="row">
    <div class="col-lg-8">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-3">Edit Pelanggan</h4>

                <form action="{{ route('tagihan.pelanggan.update', $pelanggan->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('tagihan.pelanggan._form')

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                        <a href="{{ route('tagihan.pelanggan.index') }}" class="btn btn-light">Kembali</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="mb-1">Langganan Kategori</h5>
                <p class="text-muted small mb-3">
                    Centang kategori yang pelanggan ini "berlangganan" -- setiap kali admin membuat Tagihan baru di kategori yang dicentang, pelanggan ini otomatis ikut ditagih.
                </p>

                @forelse($categories as $category)
                    @php
                        $langganan = $pelanggan->categoryPelanggan->firstWhere('tagihan_category_id', $category->id);
                        $isActive = $langganan && $langganan->status === 'active';
                    @endphp
                    <form action="{{ route('tagihan.pelanggan.category.toggle', [$pelanggan->id, $category->id]) }}" method="POST" class="d-flex align-items-center justify-content-between border rounded p-2 mb-2">
                        @csrf
                        <span>{{ $category->name }}</span>
                        <button type="submit" class="btn btn-sm {{ $isActive ? 'btn-success' : 'btn-outline-secondary' }}">
                            {{ $isActive ? 'Berlangganan' : 'Tidak Berlangganan' }}
                        </button>
                    </form>
                @empty
                    <p class="text-muted">Belum ada Kategori Tagihan di branch ini.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
