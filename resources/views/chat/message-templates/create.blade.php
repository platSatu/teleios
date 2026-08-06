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

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Periksa kembali isian berikut:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-7">
            <form action="{{ route('chat.message-templates.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('chat.message-templates._form', ['template' => null])
            </form>
        </div>
        <div class="col-lg-5 mt-4 mt-lg-0">
            @include('chat.message-templates._preview')
        </div>
    </div>
</div>

@include('chat.partials.device-select-script')
@endsection
