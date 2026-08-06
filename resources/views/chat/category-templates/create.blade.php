@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Tambah Kategori Template</h4>
            <p class="text-muted mb-0">Ajukan kategori baru untuk mengelompokkan WA Template kamu.</p>
        </div>
        <a href="{{ route('chat.category-templates.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('chat.category-templates.store') }}" method="POST">
                        @csrf
                        @include('chat.category-templates._form', ['category' => null])

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Ajukan Kategori</button>
                            <a href="{{ route('chat.category-templates.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
