@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Edit Kontak</h4>
            <p class="text-muted mb-0">Perbarui kontak "{{ $phoneBook->name }}".</p>
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

    @if ($phoneBook->is_blacklisted)
        <div class="alert alert-danger">
            <i class="ri-forbid-line"></i> Kontak ini masuk blacklist{{ $phoneBook->blacklist_reason ? ': ' . $phoneBook->blacklist_reason : '.' }}
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('chat.phone-books.update', $phoneBook->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        @include('chat.phone-books._form')

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                            <a href="{{ route('chat.phone-books.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
