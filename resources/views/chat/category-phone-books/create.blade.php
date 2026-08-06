@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Tambah Kelompok</h4>
            <p class="text-muted mb-0">Kelompok dipakai untuk mengelompokkan Buku Telepon dan sebagai pilihan tujuan pengiriman.</p>
        </div>
        <a href="{{ route('chat.category-phone-books.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('chat.category-phone-books.store') }}" method="POST">
                        @csrf
                        @include('chat.category-phone-books._form', ['category' => null])

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan Kelompok</button>
                            <a href="{{ route('chat.category-phone-books.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
