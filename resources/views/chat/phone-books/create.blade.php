@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Tambah Kontak</h4>
            <p class="text-muted mb-0">Tambahkan kontak baru ke Buku Telepon.</p>
        </div>
        <a href="{{ route('chat.phone-books.index') }}" class="btn btn-light">
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
                    <form action="{{ route('chat.phone-books.store') }}" method="POST">
                        @csrf
                        @include('chat.phone-books._form', ['phoneBook' => null])

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan Kontak</button>
                            <a href="{{ route('chat.phone-books.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
