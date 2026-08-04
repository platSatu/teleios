@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Tambah WA Template</h4>
            <p class="text-muted mb-0">Buat template pesan baru yang bisa dipakai berulang kali.</p>
        </div>
        <a href="{{ route('chat.message-templates.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('chat.message-templates.store') }}" method="POST">
                        @csrf
                        @include('chat.message-templates._form', ['template' => null])

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Simpan Template</button>
                            <a href="{{ route('chat.message-templates.index') }}" class="btn btn-light">Batal</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
