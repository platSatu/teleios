@extends('layouts.dashboard')

@section('content')
<div class="col-12">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h4 class="mb-1">Edit Jadwal Pesan</h4>
            <p class="text-muted mb-0">Perbarui jadwal "{{ $schedule->title }}".</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('chat.message-schedules.history', $schedule->id) }}" class="btn btn-light">
                <i class="ri-history-line"></i> History
            </a>
            <a href="{{ route('chat.message-schedules.index') }}" class="btn btn-light">
                <i class="ri-arrow-left-line"></i> Kembali
            </a>
        </div>
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
            <form action="{{ route('chat.message-schedules.update', $schedule->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('chat.message-schedules._form', ['schedule' => $schedule])

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="ri-save-line"></i> Simpan Perubahan</button>
                    <a href="{{ route('chat.message-schedules.index') }}" class="btn btn-light">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

@include('chat.partials.device-select-script')
@endsection
