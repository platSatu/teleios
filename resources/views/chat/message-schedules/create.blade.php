@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Tambah Jadwal Pesan</h4>
            <p class="text-muted mb-0">Jadwalkan pesan WhatsApp — sekali kirim atau berulang setiap hari, ke satu atau banyak tujuan sekaligus.</p>
        </div>
        <a href="{{ route('chat.message-schedules.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line"></i> Kembali
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form action="{{ route('chat.message-schedules.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('chat.message-schedules._form', ['schedule' => null])

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="ri-send-plane-line"></i> Simpan Jadwal</button>
                    <a href="{{ route('chat.message-schedules.index') }}" class="btn btn-light">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

@include('chat.partials.device-select-script')
@endsection
